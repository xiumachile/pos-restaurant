<?php

namespace Modules\Cashier\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Cashier\Domain\Entities\TipPayout;
use Modules\Payments\Domain\Contracts\PaymentQueryServiceInterface;

/**
 * Controller para gestión de propinas.
 * 
 * F1.4a: Refactorizado para usar PaymentQueryServiceInterface
 * en lugar de acceder directamente a DB::table('payments').
 */
class TipPayoutController extends Controller
{
    public function __construct(
        private PaymentQueryServiceInterface $paymentQueryService
    ) {
    }

    /**
     * GET /api/v1/cashier/tips/waiter/{waiterId}
     * Resumen de propinas de un garzón específico
     */
    public function waiterTips(Request $request, int $waiterId): JsonResponse
    {
        $openSession = CashSession::where('branch_id', $request->user()->branch_id)
            ->where('status', 'open')
            ->firstOrFail();

        // USAR EL SERVICIO en lugar de DB::table('payments')
        $waiterTips = $this->paymentQueryService->getWaiterTipsInSession(
            $openSession->id,
            $waiterId
        );

        $alreadyPaid = (float) TipPayout::where('cash_session_id', $openSession->id)
            ->where('waiter_id', $waiterId)
            ->valid()
            ->sum('amount');

        $pending = (float) $waiterTips - $alreadyPaid;

        return response()->json([
            'waiter_id' => $waiterId,
            'session_id' => $openSession->id,
            'tips_received' => $waiterTips,
            'already_paid' => $alreadyPaid,
            'pending' => max(0, $pending),
        ]);
    }

    /**
     * GET /api/v1/cashier/tips/summary
     * Resumen de propinas de la sesión actual
     */
    public function summary(Request $request): JsonResponse
    {
        $openSession = CashSession::where('branch_id', $request->user()->branch_id)
            ->where('status', 'open')
            ->firstOrFail();

        // USAR EL SERVICIO en lugar de DB::table('payments')
        $tipsReceived = $this->paymentQueryService->getTipsByMethodInSession($openSession->id);

        $cashTips = (float) ($tipsReceived['CASH']->total_tips ?? 0);
        $cardTips = (float) ($tipsReceived['CARD']->total_tips ?? 0);
        $transferTips = (float) ($tipsReceived['TRANSFER']->total_tips ?? 0);
        $giftCardTips = (float) ($tipsReceived['GIFT_CARD']->total_tips ?? 0);

        $totalTips = $cashTips + $cardTips + $transferTips + $giftCardTips;

        $alreadyPaid = (float) TipPayout::where('cash_session_id', $openSession->id)
            ->valid()
            ->sum('amount');

        return response()->json([
            'session_id' => $openSession->id,
            'tips_by_method' => [
                'cash' => $cashTips,
                'card' => $cardTips,
                'transfer' => $transferTips,
                'gift_card' => $giftCardTips,
            ],
            'total_tips' => $totalTips,
            'already_paid_out' => $alreadyPaid,
            'pending' => max(0, $totalTips - $alreadyPaid),
        ]);
    }

    /**
     * POST /api/v1/cashier/tips/payout
     * Registrar entrega de propinas a garzón
     */
    public function payout(Request $request): JsonResponse
    {
        $request->validate([
            'waiter_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ]);

        $openSession = CashSession::where('branch_id', $request->user()->branch_id)
            ->where('status', 'open')
            ->firstOrFail();

        // USAR EL SERVICIO en lugar de DB::table('payments')
        $waiterTips = $this->paymentQueryService->getWaiterTipsInSession(
            $openSession->id,
            $request->waiter_id
        );

        $alreadyPaid = (float) TipPayout::where('cash_session_id', $openSession->id)
            ->where('waiter_id', $request->waiter_id)
            ->valid()
            ->sum('amount');

        $available = (float) $waiterTips - $alreadyPaid;

        if ($request->amount > $available) {
            return response()->json([
                'error' => 'insufficient_tips',
                'message' => 'El monto excede las propinas disponibles',
                'available' => $available,
                'requested' => $request->amount,
            ], 422);
        }

        $payout = TipPayout::create([
            'cash_session_id' => $openSession->id,
            'waiter_id' => $request->waiter_id,
            'amount' => $request->amount,
            'paid_by' => $request->user()->id,
            'notes' => $request->notes,
            'status' => 'valid',
        ]);

        return response()->json([
            'message' => 'Propinas entregadas correctamente',
            'payout' => $payout,
        ], 201);
    }

    /**
     * GET /api/v1/cashier/tips/by-waiter
     * Propinas agrupadas por garzón
     */
    public function byWaiter(Request $request): JsonResponse
    {
        $openSession = CashSession::where('branch_id', $request->user()->branch_id)
            ->where('status', 'open')
            ->firstOrFail();

        // USAR EL SERVICIO en lugar de DB::table('payments')
        $tipsByWaiter = $this->paymentQueryService->getTipsByWaiterAndMethod($openSession->id);

        $paidOutByWaiter = TipPayout::where('cash_session_id', $openSession->id)
            ->valid()
            ->get()
            ->groupBy('waiter_id')
            ->map(fn($group) => (float) $group->sum('amount'));

        $result = [];
        foreach ($tipsByWaiter as $waiterId => $methods) {
            $totalTips = $methods->sum();
            $alreadyPaid = $paidOutByWaiter[$waiterId] ?? 0;

            $result[] = [
                'waiter_id' => $waiterId,
                'tips_by_method' => $methods->toArray(),
                'total_tips' => $totalTips,
                'already_paid' => $alreadyPaid,
                'pending' => max(0, $totalTips - $alreadyPaid),
            ];
        }

        return response()->json(['waiters' => $result]);
    }

    /**
     * GET /api/v1/cashier/tips/detailed
     * Detalle completo de propinas por garzón y método
     */
    public function detailed(Request $request): JsonResponse
    {
        $openSession = CashSession::where('branch_id', $request->user()->branch_id)
            ->where('status', 'open')
            ->firstOrFail();

        // USAR EL SERVICIO en lugar de DB::table('payments')
        $tipsByWaiter = $this->paymentQueryService->getTipsByWaiterAndMethod($openSession->id);

        $paidOutByWaiter = TipPayout::where('cash_session_id', $openSession->id)
            ->valid()
            ->get()
            ->groupBy('waiter_id')
            ->map(fn($group) => (float) $group->sum('amount'));

        $result = [];
        foreach ($tipsByWaiter as $waiterId => $methods) {
            $totalTips = $methods->sum();
            $alreadyPaid = $paidOutByWaiter[$waiterId] ?? 0;

            $result[] = [
                'waiter_id' => $waiterId,
                'tips_by_method' => $methods->toArray(),
                'total_tips' => $totalTips,
                'already_paid' => $alreadyPaid,
                'pending' => max(0, $totalTips - $alreadyPaid),
            ];
        }

        return response()->json(['waiters' => $result]);
    }
}
