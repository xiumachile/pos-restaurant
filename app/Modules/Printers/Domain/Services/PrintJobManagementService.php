<?php

namespace Modules\Printers\Domain\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Printers\Domain\Entities\Printer;
use Modules\Printers\Domain\Entities\PrintJob;
use Modules\Printers\Domain\Exceptions\PrinterConnectionException;

/**
 * Servicio de dominio para gestión operativa de trabajos de impresión.
 * 
 * Extraído de PrintJobController en S4 para cumplir DDD:
 * - Valida reglas de dominio (status check, canRetry, isAvailableForClaim)
 * - Centraliza queries con filtros
 * - Lanza DomainExceptions en lugar de retornar JSON
 * - Orquesta PrintService (envío físico) sin reemplazarlo
 */
class PrintJobManagementService
{
    public function __construct(
        private PrintService $printService
    ) {}

    /**
     * Lista trabajos de impresión con filtros.
     * 
     * @param array{status?: string, printer_uuid?: string, order_uuid?: string, limit?: int} $filters
     */
    public function listJobs(User $user, array $filters): Collection
    {
        $query = PrintJob::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->with(['printer', 'order'])
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['printer_uuid'])) {
            $printer = Printer::where('uuid', $filters['printer_uuid'])->first();
            if ($printer) {
                $query->where('printer_id', $printer->id);
            }
        }

        if (!empty($filters['order_uuid'])) {
            $order = Order::where('uuid', $filters['order_uuid'])->first();
            if ($order) {
                $query->where('order_id', $order->id);
            }
        }

        $limit = (int) ($filters['limit'] ?? 50);
        return $query->limit(min($limit, 200))->get();
    }

    /**
     * Reintenta un trabajo fallido.
     * 
     * @throws \DomainException Si el job no está failed o excedió intentos
     * @throws PrinterConnectionException Si falla el envío
     */
    public function retryJob(PrintJob $job): PrintJob
    {
        if ($job->status !== PrintJob::STATUS_FAILED) {
            throw new \DomainException(
                'Solo se pueden reintentar trabajos en estado failed. Estado actual: ' . $job->status
            );
        }

        if (!$job->canRetry()) {
            throw new \DomainException(
                "Se alcanzó el límite máximo de intentos ({$job->attempts}/{$job->max_attempts})."
            );
        }

        $job->status = PrintJob::STATUS_PENDING;
        $job->error_message = null;
        $job->save();

        $this->printService->send($job);

        return $job;
    }

    /**
     * Procesa manualmente todos los trabajos pendientes de la sucursal.
     * 
     * @return array{total: int, processed: int, failed: int, errors: array}
     */
    public function processPendingJobs(User $user): array
    {
        $pendingJobs = PrintJob::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('status', PrintJob::STATUS_PENDING)
            ->with('printer')
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        $results = [
            'total' => $pendingJobs->count(),
            'processed' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($pendingJobs as $job) {
            try {
                $this->printService->send($job);
                $results['processed']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'job_uuid' => $job->uuid,
                    'error' => $e->getMessage(),
                ];

                Log::warning('Error procesando PrintJob manualmente', [
                    'job_id' => $job->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Reclama un trabajo pendiente para imprimir localmente.
     * 
     * @throws \DomainException Si el trabajo no está disponible
     */
    public function claimJob(PrintJob $job, string $clientId): PrintJob
    {
        if (!$job->isAvailableForClaim()) {
            throw new \DomainException(
                'El trabajo no está disponible para reclamar. Estado actual: ' . $job->status
            );
        }

        $claimed = $job->claim($clientId);

        if (!$claimed) {
            throw new \DomainException('No se pudo reclamar el trabajo (race condition).');
        }

        Log::info('PrintJob reclamado por cliente local', [
            'job_uuid' => $job->uuid,
            'client_id' => $clientId,
            'printer' => $job->printer?->name,
        ]);

        return $job;
    }

    /**
     * Marca un trabajo como completado exitosamente.
     * 
     * @return array{completed: bool, already_completed: bool}
     */
    public function completeJob(PrintJob $job): array
    {
        if ($job->status === PrintJob::STATUS_COMPLETED) {
            return [
                'completed' => true,
                'already_completed' => true,
            ];
        }

        $job->markAsCompleted();

        Log::info('PrintJob completado por cliente local', [
            'job_uuid' => $job->uuid,
            'printer' => $job->printer?->name,
        ]);

        return [
            'completed' => true,
            'already_completed' => false,
        ];
    }

    /**
     * Marca un trabajo como fallido.
     * 
     * @return array{failed: bool, already_failed: bool, can_retry: bool}
     */
    public function failJob(PrintJob $job, string $errorMessage): array
    {
        if ($job->status === PrintJob::STATUS_FAILED) {
            return [
                'failed' => true,
                'already_failed' => true,
                'can_retry' => $job->canRetry(),
            ];
        }

        $job->markAsFailed($errorMessage);

        Log::warning('PrintJob fallido reportado por cliente local', [
            'job_uuid' => $job->uuid,
            'error' => $errorMessage,
            'attempts' => $job->attempts,
        ]);

        return [
            'failed' => true,
            'already_failed' => false,
            'can_retry' => $job->canRetry(),
        ];
    }
}
