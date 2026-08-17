<?php

namespace Modules\Cashier\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Cashier\Domain\Entities\TipPolicy;
use Modules\Cashier\Domain\Entities\TipPayout;
use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;

class TipPayoutController extends Controller
{
    /**
     * GET /api/v1/cashier/tip-payouts
     * Lista las entregas de propinas de la sesión abierta.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $openSession = CashSession::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('status', CashSessionStatus::OPEN)
            ->first();

        if (!$openSession) {
            return response()->json(['data' => []]);
        }

        $payouts = TipPayout::where('cash_session_id', $openSession->id)
            ->valid()
            ->with(['waiter:id,name', 'processor:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($p) => [
                'uuid' => $p->uuid,
                'waiter_name' => $p->waiter?->name,
                'processed_by_name' => $p->processor?->name,
                'amount' => (float) $p->amount,
                'payment_method' => $p->payment_method,
                'policy_type' => $p->policy_type,
                'notes' => $p->notes,
                'created_at' => $p->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $payouts]);
    }

    /**
     * POST /api/v1/cashier/tip-payouts
     * Registra una entrega de propinas a un garzón.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'waiter_id' => ['required', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,card,transfer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $openSession = CashSession::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('status', CashSessionStatus::OPEN)
            ->first();

        if (!$openSession) {
            return response()->json([
                'error' => 'no_open_session',
                'message' => 'No hay una sesión de caja abierta.',
            ], 422);
        }

        // Resolver política de propinas
        $policy = TipPolicy::resolveForBranch($user->company_id, $user->branch_id);

        // Validar política vs método de pago
        if ($validated['payment_method'] === 'card' && !$policy->cardTipsLeaveRegister()) {
            return response()->json([
                'error' => 'card_tips_to_payroll',
                'message' => 'Según la política actual, las propinas con tarjeta van a nómina, no se entregan desde caja.',
            ], 422);
        }

        $payout = TipPayout::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_session_id' => $openSession->id,
            'processed_by' => $user->id,
            'waiter_id' => $validated['waiter_id'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'policy_type' => $policy->policy_type->value,
            'notes' => $validated['notes'] ?? null,
        ]);

        $payout->load(['waiter:id,name']);

        return response()->json([
            'data' => [
                'uuid' => $payout->uuid,
                'waiter_name' => $payout->waiter?->name,
                'amount' => (float) $payout->amount,
                'payment_method' => $payout->payment_method,
                'created_at' => $payout->created_at?->toIso8601String(),
            ],
        ])->setStatusCode(201);
    }

    /**
     * DELETE /api/v1/cashier/tip-payouts/{uuid}
     * Anula una entrega de propinas.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $payout = TipPayout::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->firstOrFail();

        if ($payout->is_voided) {
            return response()->json([
                'error' => 'already_voided',
                'message' => 'Esta entrega ya fue anulada.',
            ], 422);
        }

        $payout->update([
            'is_voided' => true,
            'voided_at' => now(),
        ]);

        return response()->json([
            'data' => ['success' => true],
        ]);
    }

    /**
     * GET /api/v1/cashier/tips/summary
     * Resumen de propinas por garzón según política.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $openSession = CashSession::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('status', CashSessionStatus::OPEN)
            ->first();

        if (!$openSession) {
            return response()->json(['data' => null]);
        }

        $policy = TipPolicy::resolveForBranch($user->company_id, $user->branch_id);

        // Propinas recibidas en la sesión (de payments)
        $tipsReceived = DB::table('payments')
            ->where('cash_session_id', $openSession->id)
            ->where('status', 'completed')
            ->where('tip_amount', '>', 0)
            ->select(
                'method_code',
                DB::raw('SUM(tip_amount) as total_tips'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('method_code')
            ->get();

        $cashTips = (float) $tipsReceived->where('method_code', 'CASH')->sum('total_tips');
        $cardTips = (float) $tipsReceived->where('method_code', 'CARD')->sum('total_tips');
        $transferTips = (float) $tipsReceived->where('method_code', 'TRANSFER')->sum('total_tips');
        $giftCardTips = (float) $tipsReceived->where('method_code', 'GIFT_CARD')->sum('total_tips');
        $totalTips = $cashTips + $cardTips + $transferTips + $giftCardTips;

        // Entregas realizadas
        $payouts = TipPayout::where('cash_session_id', $openSession->id)
            ->valid()
            ->get();

        $totalPaidOut = (float) $payouts->sum('amount');
        $cashPaidOut = (float) $payouts->where('payment_method', 'cash')->sum('amount');

        // Propinas pendientes de entregar
        $pendingTips = max(0, $totalTips - $totalPaidOut);

        // Resumen por garzón
        $waiterSummary = $payouts
            ->groupBy('waiter_id')
            ->map(function ($group) {
                return [
                    'waiter_id' => $group->first()->waiter_id,
                    'waiter_name' => $group->first()->waiter?->name,
                    'total_amount' => (float) $group->sum('amount'),
                    'cash_amount' => (float) $group->where('payment_method', 'cash')->sum('amount'),
                    'payout_count' => $group->count(),
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'policy' => [
                    'type' => $policy->policy_type->value,
                    'label' => $policy->policy_type->label(),
                    'card_tip_handling' => $policy->card_tip_handling->value,
                ],
                'tips_received' => [
                    'cash' => $cashTips,
                    'card' => $cardTips,
                    'transfer' => $transferTips,
                    'gift_card' => $giftCardTips,
                    'total' => $totalTips,
                ],
                'payouts' => [
                    'total' => $totalPaidOut,
                    'cash' => $cashPaidOut,
                    'count' => $payouts->count(),
                ],
                'pending' => $pendingTips,
                'by_waiter' => $waiterSummary,
            ],
        ]);
    }

    /**
     * GET /api/v1/cashier/waiters
     * Lista garzones disponibles para entregar propinas.
     */
    public function waiters(Request $request): JsonResponse
    {
        $user = $request->user();

        $waiters = User::where('company_id', $user->company_id)
            ->whereIn('role', ['waiter', 'admin', 'manager'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return response()->json(['data' => $waiters]);
    }
}
