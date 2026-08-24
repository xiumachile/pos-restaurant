<?php

namespace Modules\Printers\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Printers\Domain\Entities\PrintJob;
use Modules\Printers\Domain\Exceptions\PrinterConnectionException;
use Modules\Printers\Domain\Services\PrintService;
use Modules\Printers\Interfaces\Resources\PrintJobResource;

class PrintJobController extends Controller
{
    public function __construct(
        private PrintService $printService
    ) {}

    /**
     * GET /api/v1/print-jobs
     * Lista trabajos de impresión con filtros.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = PrintJob::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->with(['printer', 'order'])
            ->orderByDesc('created_at');

        // Filtros opcionales
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($printerUuid = $request->query('printer_uuid')) {
            $printer = \Modules\Printers\Domain\Entities\Printer::where('uuid', $printerUuid)->first();
            if ($printer) {
                $query->where('printer_id', $printer->id);
            }
        }
        if ($orderUuid = $request->query('order_uuid')) {
            $order = \Modules\Orders\Domain\Entities\Order::where('uuid', $orderUuid)->first();
            if ($order) {
                $query->where('order_id', $order->id);
            }
        }

        $limit = (int) $request->query('limit', 50);
        $jobs = $query->limit(min($limit, 200))->get();

        return PrintJobResource::collection($jobs)->response();
    }

    /**
     * GET /api/v1/print-jobs/{uuid}
     * Obtiene detalle de un trabajo específico.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $job = PrintJob::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->with(['printer', 'order'])
            ->firstOrFail();

        return PrintJobResource::make($job)->response();
    }

    /**
     * POST /api/v1/print-jobs/{uuid}/retry
     * Reintenta un trabajo fallido.
     */
    public function retry(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $job = PrintJob::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->with('printer')
            ->firstOrFail();

        if ($job->status !== PrintJob::STATUS_FAILED) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden reintentar trabajos en estado failed.',
                'current_status' => $job->status,
            ], 422);
        }

        if (!$job->canRetry()) {
            return response()->json([
                'success' => false,
                'message' => 'Se alcanzó el límite máximo de intentos.',
                'attempts' => $job->attempts,
                'max_attempts' => $job->max_attempts,
            ], 422);
        }

        // Resetear estado para reintento
        $job->status = PrintJob::STATUS_PENDING;
        $job->error_message = null;
        $job->save();

        try {
            $this->printService->send($job);

            return response()->json([
                'success' => true,
                'message' => 'Trabajo reintentado y enviado exitosamente.',
                'job_uuid' => $job->uuid,
                'status' => $job->status,
            ]);
        } catch (PrinterConnectionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión al reintentar: ' . $e->getMessage(),
                'job_uuid' => $job->uuid,
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al reintentar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/print-jobs/process
     * Procesa manualmente todos los trabajos pendientes de la sucursal.
     */
    public function process(Request $request): JsonResponse
    {
        $user = $request->user();

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

        return response()->json([
            'success' => true,
            'message' => "Procesamiento completado: {$results['processed']} exitosos, {$results['failed']} fallidos.",
            'results' => $results,
        ]);
    }

    /**
     * POST /api/v1/print-jobs/{uuid}/claim
     * Reclama un trabajo pendiente para imprimir localmente.
     * 
     * @param string $clientId Identificador único del cliente (ej: Tauri device ID)
     */
    public function claim(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $clientId = $request->input('client_id', 'tauri-' . $user->id);

        $job = PrintJob::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->with(['printer', 'order'])
            ->firstOrFail();

        if (!$job->isAvailableForClaim()) {
            return response()->json([
                'success' => false,
                'message' => 'El trabajo no está disponible para reclamar.',
                'current_status' => $job->status,
                'claimed_by' => $job->claimed_by,
                'claimed_at' => $job->claimed_at?->toIso8601String(),
            ], 409);
        }

        $claimed = $job->claim($clientId);

        if (!$claimed) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo reclamar el trabajo (race condition).',
            ], 409);
        }

        Log::info('PrintJob reclamado por cliente local', [
            'job_uuid' => $job->uuid,
            'client_id' => $clientId,
            'printer' => $job->printer?->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trabajo reclamado exitosamente.',
            'job' => new PrintJobResource($job),
        ]);
    }

    /**
     * POST /api/v1/print-jobs/{uuid}/complete
     * Marca un trabajo como completado exitosamente.
     */
    public function complete(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $job = PrintJob::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->with('printer')
            ->firstOrFail();

        if ($job->status === PrintJob::STATUS_COMPLETED) {
            return response()->json([
                'success' => true,
                'message' => 'El trabajo ya estaba completado.',
            ]);
        }

        $job->markAsCompleted();

        Log::info('PrintJob completado por cliente local', [
            'job_uuid' => $job->uuid,
            'printer' => $job->printer?->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trabajo marcado como completado.',
            'job_uuid' => $job->uuid,
            'status' => $job->status,
            'printed_at' => $job->printed_at?->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/print-jobs/{uuid}/fail
     * Marca un trabajo como fallido.
     */
    public function fail(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $errorMessage = $request->input('error_message', 'Error desconocido del cliente');

        $job = PrintJob::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        if ($job->status === PrintJob::STATUS_FAILED) {
            return response()->json([
                'success' => true,
                'message' => 'El trabajo ya estaba marcado como fallido.',
            ]);
        }

        $job->markAsFailed($errorMessage);

        Log::warning('PrintJob fallido reportado por cliente local', [
            'job_uuid' => $job->uuid,
            'error' => $errorMessage,
            'attempts' => $job->attempts,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trabajo marcado como fallido.',
            'job_uuid' => $job->uuid,
            'status' => $job->status,
            'error_message' => $job->error_message,
            'can_retry' => $job->canRetry(),
        ]);
    }
}
