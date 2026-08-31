<?php

namespace Modules\Payments\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Exceptions\PaymentException;
use Modules\Payments\Domain\Services\CashSessionService;
use Modules\Payments\Interfaces\Requests\CloseCashSessionRequest;
use Modules\Payments\Interfaces\Requests\OpenCashSessionRequest;
use Modules\Payments\Interfaces\Resources\CashSessionResource;

class CashSessionController extends Controller
{
    public function __construct(
        private CashSessionService $cashSessionService
    ) {}

    /**
     * POST /api/v1/cash-sessions/open
     * Abre una nueva sesión de caja.
     * 
     * AUTORIZACIÓN: Solo cashier/admin/manager pueden abrir sesión.
     * La sesión se crea en la branch del usuario autenticado.
     */
    public function open(OpenCashSessionRequest $request): JsonResponse
    {
        // Autorización: verificar que el usuario puede abrir sesión
        $this->authorize('open', CashSession::class);

        // Abrir caja es OPCIONAL para todas las empresas.
        // La capability requires_cashier_session determina si es OBLIGATORIO
        // tener una sesión abierta antes de aceptar pagos, no si se puede abrir caja.
        $validated = $request->validated();
        $user = $request->user();

        try {
            $session = $this->cashSessionService->openSession(
                companyId: $user->company_id,
                branchId: $user->branch_id,
                userId: $user->id,
                openingAmount: (float) $validated['opening_amount'],
                notes: $validated['notes'] ?? null
            );

            return CashSessionResource::make($session)
                ->response()
                ->setStatusCode(201);
        } catch (PaymentException $e) {
            return response()->json([
                'error' => 'session_open_failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/cash-sessions/{uuid}/close
     * Cierra una sesión de caja con arqueo.
     * 
     * AUTORIZACIÓN: Solo cashier/admin/manager pueden cerrar sesión.
     * SEGURIDAD: Solo pueden cerrar sesiones de su propia branch (cross-branch isolation).
     */
    public function close(CloseCashSessionRequest $request, string $uuid): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        
        // Defensa en profundidad: filtrar por company_id Y branch_id
        $session = CashSession::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->firstOrFail();

        // Autorización: verificar que el usuario puede cerrar esta sesión específica
        $this->authorize('close', $session);

        try {
            $session = $this->cashSessionService->closeSession(
                session: $session,
                closingAmount: (float) $validated['closing_amount'],
                notes: $validated['notes'] ?? null
            );

            return CashSessionResource::make($session)->response();
        } catch (PaymentException $e) {
            return response()->json([
                'error' => 'session_close_failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/v1/cash-sessions/current
     * Obtiene la sesión abierta actual de la branch del usuario.
     * 
     * AUTORIZACIÓN: Cualquier rol operativo puede ver la sesión actual.
     */
    public function current(Request $request): JsonResponse
    {
        // Autorización: verificar que el usuario puede ver la sesión actual
        $this->authorize('viewCurrent', CashSession::class);

        $user = $request->user();
        $session = $this->cashSessionService->getOpenSession($user->branch_id);

        if (!$session) {
            return response()->json(['data' => null]);
        }

        return CashSessionResource::make($session)->response();
    }
}
