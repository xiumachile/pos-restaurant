<?php

namespace Modules\Printers\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Printers\Domain\Entities\PrintJob;
use Modules\Printers\Domain\Exceptions\PrinterConnectionException;
use Modules\Printers\Domain\Services\PrintJobManagementService;
use Modules\Printers\Interfaces\Resources\PrintJobResource;

/**
 * Controller para gestión de trabajos de impresión.
 * 
 * Refactorizado en S4: toda la lógica de negocio delegada a PrintJobManagementService.
 * Este controller solo orquesta HTTP: valida inputs, delega al service, retorna JSON.
 */
class PrintJobController extends Controller
{
    public function __construct(
        private PrintJobManagementService $printJobService
    ) {}

    /**
     * GET /api/v1/print-jobs
     * Lista trabajos de impresión con filtros.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['status', 'printer_uuid', 'order_uuid', 'limit']);

        $jobs = $this->printJobService->listJobs($user, $filters);

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

        try {
            $this->printJobService->retryJob($job);

            return response()->json([
                'success' => true,
                'message' => 'Trabajo reintentado y enviado exitosamente.',
                'job_uuid' => $job->uuid,
                'status' => $job->status,
            ]);

        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'job_uuid' => $job->uuid,
            ], 422);

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
        $results = $this->printJobService->processPendingJobs($user);

        return response()->json([
            'success' => true,
            'message' => "Procesamiento completado: {$results['processed']} exitosos, {$results['failed']} fallidos.",
            'results' => $results,
        ]);
    }

    /**
     * POST /api/v1/print-jobs/{uuid}/claim
     * Reclama un trabajo pendiente para imprimir localmente.
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

        try {
            $this->printJobService->claimJob($job, $clientId);

            return response()->json([
                'success' => true,
                'message' => 'Trabajo reclamado exitosamente.',
                'job' => new PrintJobResource($job),
            ]);

        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'current_status' => $job->status,
                'claimed_by' => $job->claimed_by,
                'claimed_at' => $job->claimed_at?->toIso8601String(),
            ], 409);
        }
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

        $result = $this->printJobService->completeJob($job);

        if ($result['already_completed']) {
            return response()->json([
                'success' => true,
                'message' => 'El trabajo ya estaba completado.',
            ]);
        }

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

        $result = $this->printJobService->failJob($job, $errorMessage);

        if ($result['already_failed']) {
            return response()->json([
                'success' => true,
                'message' => 'El trabajo ya estaba marcado como fallido.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Trabajo marcado como fallido.',
            'job_uuid' => $job->uuid,
            'status' => $job->status,
            'error_message' => $job->error_message,
            'can_retry' => $result['can_retry'],
        ]);
    }
}
