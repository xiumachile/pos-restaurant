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
     */
    public function open(OpenCashSessionRequest $request): JsonResponse
    {
        // Verificar que la empresa tenga habilitado requires_cashier_session
        if (!$request->user()->company->hasCapability('requires_cashier_session')) {
            return response()->json([
                'error' => 'capability_not_enabled',
                'message' => 'Esta empresa no requiere apertura de caja',
                'required_capability' => 'requires_cashier_session',
            ], 403);
        }

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
     */
    public function close(CloseCashSessionRequest $request, string $uuid): JsonResponse
    {
        $validated = $request->validated();
        $session = CashSession::where('uuid', $uuid)
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();

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
     * Obtiene la sesión abierta actual de la sucursal.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $session = $this->cashSessionService->getOpenSession($user->branch_id);

        if (!$session) {
            return response()->json(['data' => null]);
        }

        return CashSessionResource::make($session)->response();
    }
}
