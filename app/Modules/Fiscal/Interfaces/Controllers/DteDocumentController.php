<?php

namespace Modules\Fiscal\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Fiscal\Domain\Entities\DteDocument;
use Modules\Fiscal\Domain\Exceptions\NoFoliosAvailableException;
use Modules\Fiscal\Domain\Services\DteDocumentManagementService;
use Modules\Fiscal\Interfaces\Requests\IssueDteRequest;
use Modules\Fiscal\Interfaces\Resources\DteDocumentResource;
use Modules\Orders\Domain\Entities\Order;

/**
 * Controller para gestión de DTEs (Documentos Tributarios Electrónicos).
 * 
 * Refactorizado en S3: toda la lógica de negocio delegada a DteDocumentManagementService.
 * Este controller solo orquesta HTTP: valida inputs, delega al service, retorna JSON.
 */
class DteDocumentController extends Controller
{
    public function __construct(
        private DteDocumentManagementService $dteService
    ) {}

    /**
     * GET /api/v1/fiscal/dtes
     * Lista DTEs con filtros.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['dte_type', 'status', 'start_date', 'end_date', 'limit']);

        $dtes = $this->dteService->listDtes($user, $filters);

        return DteDocumentResource::collection($dtes)->response();
    }

    /**
     * POST /api/v1/fiscal/dtes
     * Emite un DTE manualmente para un pedido.
     */
    public function store(IssueDteRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $order = Order::where('uuid', $validated['order_uuid'])
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        try {
            $environment = $validated['environment'] ?? 'certification';
            $receiverRut = $validated['receiver_rut'] ?? null;
            $receiverName = $validated['receiver_business_name'] ?? null;

            $dte = $this->dteService->issueDte($order, $user, $environment, $receiverRut, $receiverName);

            return DteDocumentResource::make($dte)
                ->response()
                ->setStatusCode(201);

        } catch (NoFoliosAvailableException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error emitiendo DTE manualmente', [
                'order_uuid' => $validated['order_uuid'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al emitir DTE: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/fiscal/dtes/{uuid}
     * Obtiene detalle de un DTE.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $dte = DteDocument::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->with(['order', 'referencedDte'])
            ->firstOrFail();

        return DteDocumentResource::make($dte)->response();
    }

    /**
     * POST /api/v1/fiscal/dtes/{uuid}/cancel
     * Anula un DTE emitiendo una Nota de Crédito.
     */
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $reason = $request->input('reason', 'Anulación solicitada por usuario');

        $dte = DteDocument::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        try {
            $result = $this->dteService->cancelDte($dte, $user, $reason);

            return response()->json([
                'success' => true,
                'message' => 'DTE anulado correctamente con Nota de Crédito.',
                'original_dte' => $result['original_dte'],
                'cancellation_note' => $result['cancellation_note'],
                'nc_status' => $result['nc_status'],
            ]);

        } catch (\DomainException | \RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al anular DTE: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/fiscal/dtes/{uuid}/resend
     * Reintenta envío de un DTE fallido.
     */
    public function resend(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $dte = DteDocument::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        try {
            $result = $this->dteService->resendDte($dte, $user);

            return response()->json([
                'success' => $result['sent'],
                'message' => $result['sent'] ? 'DTE reenviado exitosamente.' : 'Error al reenviar DTE.',
                'dte_status' => $result['dte_status'],
                'track_id' => $result['track_id'],
            ]);

        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
