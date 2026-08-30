<?php

namespace Modules\Cashier\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cashier\Domain\Services\TipPayoutService;
use Modules\Cashier\Interfaces\Requests\CreateTipPayoutRequest;
use Modules\Cashier\Interfaces\Resources\TipPayoutResource;

class TipPayoutController extends Controller
{
    public function __construct(
        private TipPayoutService $tipPayoutService
    ) {}

    /**
     * GET /api/v1/cashier/tip-payouts
     * Lista todas las entregas de propinas de la sesión actual.
     */
    public function index(Request $request): JsonResponse
    {
        $session = $this->tipPayoutService->getOpenSession($request->user()->branch_id);

        if (!$session) {
            return response()->json([
                'error' => 'no_open_session',
                'message' => 'No hay sesión de caja abierta.',
            ], 422);
        }

        $payouts = $this->tipPayoutService->listPayouts($session->id);

        return TipPayoutResource::collection($payouts)->response();
    }

    /**
     * POST /api/v1/cashier/tip-payouts
     * Crea una entrega manual de propinas.
     */
    public function store(CreateTipPayoutRequest $request): JsonResponse
    {
        $session = $this->tipPayoutService->getOpenSession($request->user()->branch_id);

        if (!$session) {
            return response()->json([
                'error' => 'no_open_session',
                'message' => 'No hay sesión de caja abierta.',
            ], 422);
        }

        try {
            $payout = $this->tipPayoutService->createPayout(
                $session,
                $request->waiter_id,
                $request->amount,
                $request->user()->id,
                $request->payment_method ?? 'cash',
                $request->notes
            );

            return TipPayoutResource::make($payout)
                ->response()
                ->setStatusCode(201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'insufficient_tips',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * DELETE /api/v1/cashier/tip-payouts/{uuid}
     * Anula una entrega de propinas.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $voided = $this->tipPayoutService->voidPayout($uuid, $request->user()->company_id);

        if (!$voided) {
            return response()->json([
                'error' => 'already_voided',
                'message' => 'La entrega ya fue anulada.',
            ], 422);
        }

        return response()->json([
            'message' => 'Entrega anulada correctamente.',
        ]);
    }

    /**
     * GET /api/v1/cashier/tips/summary
     * Resumen de propinas de la sesión actual.
     */
    public function summary(Request $request): JsonResponse
    {
        $session = $this->tipPayoutService->getOpenSession($request->user()->branch_id);

        if (!$session) {
            return response()->json([
                'error' => 'no_open_session',
                'message' => 'No hay sesión de caja abierta.',
            ], 422);
        }

        $summary = $this->tipPayoutService->getSessionSummary($session->id);

        return response()->json([
            'data' => array_merge(
                ['session_id' => $session->id],
                $summary
            ),
        ]);
    }

    /**
     * GET /api/v1/cashier/tips/max-by-waiter
     * Máximo pendiente por garzón (para validación rápida).
     */
    public function maxByWaiter(Request $request): JsonResponse
    {
        $session = $this->tipPayoutService->getOpenSession($request->user()->branch_id);

        if (!$session) {
            return response()->json([
                'error' => 'no_open_session',
                'message' => 'No hay sesión de caja abierta.',
            ], 422);
        }

        $maxByWaiter = $this->tipPayoutService->getMaxByWaiter($session->id);

        return response()->json([
            'data' => $maxByWaiter,
        ]);
    }

    /**
     * GET /api/v1/cashier/waiters
     * Lista garzones con actividad en la sesión actual.
     */
    public function waiters(Request $request): JsonResponse
    {
        $session = $this->tipPayoutService->getOpenSession($request->user()->branch_id);

        if (!$session) {
            return response()->json([
                'error' => 'no_open_session',
                'message' => 'No hay sesión de caja abierta.',
            ], 422);
        }

        $summary = $this->tipPayoutService->getWaitersSummary($session->id);

        return response()->json([
            'data' => $summary,
        ]);
    }

    /**
     * GET /api/v1/cashier/tips/by-waiter
     * Propinas agrupadas por garzón y método de pago.
     */
    public function byWaiter(Request $request): JsonResponse
    {
        $session = $this->tipPayoutService->getOpenSession($request->user()->branch_id);

        if (!$session) {
            return response()->json([
                'error' => 'no_open_session',
                'message' => 'No hay sesión de caja abierta.',
            ], 422);
        }

        $summary = $this->tipPayoutService->getWaitersSummary($session->id);

        return response()->json([
            'data' => $summary,
        ]);
    }

    /**
     * POST /api/v1/cashier/tips/generate-payouts
     * Genera automáticamente entregas para todos los garzones.
     */
    public function generatePayouts(Request $request): JsonResponse
    {
        $session = $this->tipPayoutService->getOpenSession($request->user()->branch_id);

        if (!$session) {
            return response()->json([
                'error' => 'no_open_session',
                'message' => 'No hay sesión de caja abierta.',
            ], 422);
        }

        $payouts = $this->tipPayoutService->generatePayouts($session, $request->user()->id);

        return response()->json([
            'message' => 'Entregas generadas correctamente.',
            'count' => count($payouts),
            'payouts' => TipPayoutResource::collection($payouts),
        ], 201);
    }
}
