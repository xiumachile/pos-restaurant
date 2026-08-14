<?php

namespace Modules\Fiscal\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Fiscal\Domain\Entities\DteCertificate;
use Modules\Fiscal\Domain\Entities\DteDocument;
use Modules\Fiscal\Domain\Services\DteIssuingService;
use Modules\Fiscal\Domain\Services\DteSendingService;
use Modules\Fiscal\Domain\ValueObjects\DteStatus;
use Modules\Fiscal\Interfaces\Requests\IssueDteRequest;
use Modules\Fiscal\Interfaces\Resources\DteDocumentResource;
use Modules\Orders\Domain\Entities\Order;

class DteDocumentController extends Controller
{
    public function __construct(
        private DteIssuingService $issuingService,
        private DteSendingService $sendingService
    ) {}

    /**
     * GET /api/v1/fiscal/dtes
     * Lista DTEs con filtros.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = DteDocument::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->with(['order'])
            ->orderByDesc('issue_date')
            ->orderByDesc('folio');

        // Filtros
        if ($dteType = $request->query('dte_type')) {
            $query->where('dte_type', (int) $dteType);
        }
        if ($status = $request->query('status')) {
            $query->where('sii_status', $status);
        }
        if ($startDate = $request->query('start_date')) {
            $query->whereDate('issue_date', '>=', $startDate);
        }
        if ($endDate = $request->query('end_date')) {
            $query->whereDate('issue_date', '<=', $endDate);
        }

        $limit = (int) $request->query('limit', 50);
        $dtes = $query->limit(min($limit, 200))->get();

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

        // Verificar que el pedido esté pagado
        if ($order->status->value !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden emitir DTEs para pedidos pagados.',
                'order_status' => $order->status->value,
            ], 422);
        }

        // Verificar que no exista DTE para este pedido
        $existingDte = DteDocument::where('order_id', $order->id)
            ->where('company_id', $user->company_id)
            ->whereNotIn('sii_status', [DteStatus::CANCELLED, DteStatus::REJECTED])
            ->first();

        if ($existingDte) {
            return response()->json([
                'success' => false,
                'message' => 'El pedido ya tiene un DTE emitido.',
                'existing_dte' => $existingDte->identifier(),
            ], 422);
        }

        try {
            $environment = $validated['environment'] ?? 'certification';
            $receiverRut = $validated['receiver_rut'] ?? null;
            $receiverName = $validated['receiver_business_name'] ?? null;

            $dte = $this->issuingService->issueForOrder(
                $order,
                $receiverRut,
                $receiverName,
                $environment
            );

            // Enviar al SII
            $certificate = DteCertificate::where('company_id', $user->company_id)
                ->where('environment', $environment)
                ->where('is_active', true)
                ->where('valid_until', '>=', now())
                ->first();

            if ($certificate) {
                $this->sendingService->send($dte, $certificate, $environment);
                $dte->refresh();
            }

            return DteDocumentResource::make($dte)
                ->response()
                ->setStatusCode(201);

        } catch (\Modules\Fiscal\Domain\Exceptions\NoFoliosAvailableException $e) {
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

        $dte = DteDocument::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $reason = $request->input('reason', 'Anulación solicitada por usuario');

        try {
            $nc = $this->issuingService->issueCancellationNote($dte, $reason);

            // Enviar NC al SII
            $certificate = DteCertificate::where('company_id', $user->company_id)
                ->where('is_active', true)
                ->where('valid_until', '>=', now())
                ->first();

            if ($certificate) {
                $this->sendingService->send($nc, $certificate, 'certification');
                $nc->refresh();
            }

            return response()->json([
                'success' => true,
                'message' => 'DTE anulado correctamente con Nota de Crédito.',
                'original_dte' => $dte->identifier(),
                'cancellation_note' => $nc->identifier(),
                'nc_status' => $nc->sii_status->value,
            ]);

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

        if (!$dte->sii_status->canBeResent()) {
            return response()->json([
                'success' => false,
                'message' => 'El DTE no puede ser reenviado en estado: ' . $dte->sii_status->label(),
            ], 422);
        }

        $certificate = DteCertificate::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->where('valid_until', '>=', now())
            ->first();

        if (!$certificate) {
            return response()->json([
                'success' => false,
                'message' => 'No hay certificado válido para reenviar.',
            ], 422);
        }

        $sent = $this->sendingService->send($dte, $certificate, 'certification');
        $dte->refresh();

        return response()->json([
            'success' => $sent,
            'message' => $sent ? 'DTE reenviado exitosamente.' : 'Error al reenviar DTE.',
            'dte_status' => $dte->sii_status->value,
            'track_id' => $dte->track_id,
        ]);
    }
}
