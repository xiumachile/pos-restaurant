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

        // VALIDACIÓN: calcular propinas pendientes del garzón
        $waiterId = $validated['waiter_id'];
        $amount = (float) $validated['amount'];

        // Obtener propinas recibidas por el garzón
        $waiterTips = DB::table('payments')
            ->where('payments.cash_session_id', $openSession->id)
            ->where('payments.status', 'completed')
            ->where('payments.tip_amount', '>', 0)
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->where('orders.waiter_id', $waiterId)
            ->sum('payments.tip_amount');

        // Calcular propinas ya entregadas al garzón
        $alreadyPaid = (float) TipPayout::where('cash_session_id', $openSession->id)
            ->where('waiter_id', $waiterId)
            ->valid()
            ->sum('amount');

        $pending = (float) $waiterTips - $alreadyPaid;

        // Validar que no se entregue más de lo pendiente
        if ($amount > $pending + 0.01) {
            return response()->json([
                'error' => 'amount_exceeds_pending',
                'message' => "No puedes entregar más de lo pendiente. Pendiente: \${$pending}",
                'pending' => $pending,
                'requested' => $amount,
            ], 422);
        }

        // Validar que haya algo pendiente
        if ($pending <= 0.01) {
            return response()->json([
                'error' => 'no_pending_tips',
                'message' => 'Este garzón no tiene propinas pendientes de entregar.',
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

    /**
     * GET /api/v1/cashier/tips/by-waiter
     * Calcula propinas pendientes por garzón según política configurada.
     * Este endpoint se usa en el wizard de cierre.
     */
    public function byWaiter(Request $request): JsonResponse
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

        // Obtener propinas de payments de la sesión
        $paymentsWithTips = DB::table('payments')
            ->where('payments.cash_session_id', $openSession->id)
            ->where('payments.status', 'completed')
            ->where('payments.tip_amount', '>', 0)
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->select(
                'orders.waiter_id',
                'payments.method_code',
                'payments.tip_amount'
            )
            ->get();

        // Propinas ya entregadas en la sesión
        $paidOutByWaiter = TipPayout::where('cash_session_id', $openSession->id)
            ->valid()
            ->get()
            ->groupBy('waiter_id')
            ->map(fn($group) => (float) $group->sum('amount'));

        // Agrupar propinas por garzón y método
        $tipsByWaiter = [];
        foreach ($paymentsWithTips as $payment) {
            $waiterId = $payment->waiter_id ?? 0;
            
            if (!isset($tipsByWaiter[$waiterId])) {
                $tipsByWaiter[$waiterId] = [
                    'waiter_id' => $waiterId,
                    'cash' => 0,
                    'card' => 0,
                    'transfer' => 0,
                    'gift_card' => 0,
                    'total' => 0,
                ];
            }

            $method = strtolower($payment->method_code);
            if (isset($tipsByWaiter[$waiterId][$method])) {
                $tipsByWaiter[$waiterId][$method] += (float) $payment->tip_amount;
            }
            $tipsByWaiter[$waiterId]['total'] += (float) $payment->tip_amount;
        }

        // Aplicar política de reparto
        $result = [];
        
        if ($policy->policy_type->value === 'shared_pool') {
            // Pozo común: dividir total entre todos los garzones con pedidos
            $totalCash = array_sum(array_column($tipsByWaiter, 'cash'));
            $totalCard = array_sum(array_column($tipsByWaiter, 'card'));
            $totalTransfer = array_sum(array_column($tipsByWaiter, 'transfer'));
            $totalGiftCard = array_sum(array_column($tipsByWaiter, 'gift_card'));
            $grandTotal = array_sum(array_column($tipsByWaiter, 'total'));
            
            $waiterCount = count($tipsByWaiter);
            if ($waiterCount > 0) {
                $perWaiter = $grandTotal / $waiterCount;
                
                foreach ($tipsByWaiter as $waiterId => $tips) {
                    $waiter = User::find($waiterId);
                    $alreadyPaid = $paidOutByWaiter->get($waiterId, 0);
                    $pending = $perWaiter - $alreadyPaid;
                    
                    if ($pending > 0.01) {
                        $result[] = [
                            'waiter_id' => $waiterId,
                            'waiter_name' => $waiter?->name ?? 'Sin asignar',
                            'cash' => round($totalCash / $waiterCount, 2),
                            'card' => round($totalCard / $waiterCount, 2),
                            'transfer' => round($totalTransfer / $waiterCount, 2),
                            'gift_card' => round($totalGiftCard / $waiterCount, 2),
                            'total' => round($perWaiter, 2),
                            'already_paid' => $alreadyPaid,
                            'pending' => round($pending, 2),
                        ];
                    }
                }
            }
        } else {
            // waiter_keeps o percentage_split: cada garzón recibe sus propinas
            foreach ($tipsByWaiter as $waiterId => $tips) {
                $waiter = User::find($waiterId);
                $alreadyPaid = $paidOutByWaiter->get($waiterId, 0);
                $pending = $tips['total'] - $alreadyPaid;
                
                if ($pending > 0.01) {
                    $result[] = [
                        'waiter_id' => $waiterId,
                        'waiter_name' => $waiter?->name ?? 'Sin asignar',
                        'cash' => $tips['cash'],
                        'card' => $tips['card'],
                        'transfer' => $tips['transfer'],
                        'gift_card' => $tips['gift_card'],
                        'total' => $tips['total'],
                        'already_paid' => $alreadyPaid,
                        'pending' => round($pending, 2),
                    ];
                }
            }
        }

        $totalPending = array_sum(array_column($result, 'pending'));

        return response()->json([
            'data' => [
                'policy' => [
                    'type' => $policy->policy_type->value,
                    'label' => $policy->policy_type->label(),
                    'card_tip_handling' => $policy->card_tip_handling->value,
                ],
                'by_waiter' => $result,
                'total_pending' => $totalPending,
                'total_pending_cash' => array_sum(array_column($result, 'cash')),
            ],
        ]);
    }

    /**
     * POST /api/v1/cashier/tips/generate-payouts
     * Genera automáticamente las entregas de propinas pendientes.
     * Se usa en el wizard de cierre.
     */
    public function generatePayouts(Request $request): JsonResponse
    {
        $user = $request->user();

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

        // Calcular propinas pendientes (misma lógica que byWaiter)
        $policy = TipPolicy::resolveForBranch($user->company_id, $user->branch_id);

        $paymentsWithTips = DB::table('payments')
            ->where('payments.cash_session_id', $openSession->id)
            ->where('payments.status', 'completed')
            ->where('payments.tip_amount', '>', 0)
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->select('orders.waiter_id', 'payments.method_code', 'payments.tip_amount')
            ->get();

        $paidOutByWaiter = TipPayout::where('cash_session_id', $openSession->id)
            ->valid()
            ->get()
            ->groupBy('waiter_id')
            ->map(fn($group) => (float) $group->sum('amount'));

        $tipsByWaiter = [];
        foreach ($paymentsWithTips as $payment) {
            $waiterId = $payment->waiter_id ?? 0;
            if (!isset($tipsByWaiter[$waiterId])) {
                $tipsByWaiter[$waiterId] = ['cash' => 0, 'card' => 0, 'transfer' => 0, 'total' => 0];
            }
            $method = strtolower($payment->method_code);
            if ($method === 'gift_card') $method = 'card';
            if (isset($tipsByWaiter[$waiterId][$method])) {
                $tipsByWaiter[$waiterId][$method] += (float) $payment->tip_amount;
            }
            $tipsByWaiter[$waiterId]['total'] += (float) $payment->tip_amount;
        }

        $createdPayouts = [];
        
        foreach ($tipsByWaiter as $waiterId => $tips) {
            $alreadyPaid = $paidOutByWaiter->get($waiterId, 0);
            $pending = $tips['total'] - $alreadyPaid;
            
            if ($pending > 0.01 && $waiterId > 0) {
                // Determinar método de pago según política
                // Si la política es cash_payout, todo se paga en efectivo
                // Si es payroll, solo el efectivo se entrega, tarjeta va a nómina
                $cashAmount = $policy->cardTipsLeaveRegister() 
                    ? $pending 
                    : $tips['cash'] - $alreadyPaid;
                
                if ($cashAmount > 0.01) {
                    $payout = TipPayout::create([
                        'company_id' => $user->company_id,
                        'branch_id' => $user->branch_id,
                        'cash_session_id' => $openSession->id,
                        'processed_by' => $user->id,
                        'waiter_id' => $waiterId,
                        'amount' => round($cashAmount, 2),
                        'payment_method' => 'cash',
                        'policy_type' => $policy->policy_type->value,
                        'notes' => 'Generado automáticamente en cierre de caja',
                    ]);
                    
                    $waiter = User::find($waiterId);
                    $createdPayouts[] = [
                        'uuid' => $payout->uuid,
                        'waiter_name' => $waiter?->name,
                        'amount' => (float) $payout->amount,
                        'payment_method' => $payout->payment_method,
                    ];
                }
            }
        }

        return response()->json([
            'data' => [
                'payouts_created' => count($createdPayouts),
                'total_amount' => array_sum(array_column($createdPayouts, 'amount')),
                'payouts' => $createdPayouts,
            ],
        ]);
    }

    /**
     * GET /api/v1/cashier/tips/max-by-waiter
     * Devuelve el máximo de propina pendiente por garzón (para validación frontend)
     */
    public function maxByWaiter(Request $request): JsonResponse
    {
        $user = $request->user();

        $openSession = CashSession::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('status', CashSessionStatus::OPEN)
            ->first();

        if (!$openSession) {
            return response()->json(['data' => []]);
        }

        // Propinas por garzón
        $tipsByWaiter = DB::table('payments')
            ->where('payments.cash_session_id', $openSession->id)
            ->where('payments.status', 'completed')
            ->where('payments.tip_amount', '>', 0)
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->whereNotNull('orders.waiter_id')
            ->select('orders.waiter_id', DB::raw('SUM(payments.tip_amount) as total'))
            ->groupBy('orders.waiter_id')
            ->pluck('total', 'waiter_id');

        // Ya pagado por garzón
        $paidByWaiter = TipPayout::where('cash_session_id', $openSession->id)
            ->valid()
            ->select('waiter_id', DB::raw('SUM(amount) as total'))
            ->groupBy('waiter_id')
            ->pluck('total', 'waiter_id');

        $result = [];
        foreach ($tipsByWaiter as $waiterId => $total) {
            $paid = (float) ($paidByWaiter[$waiterId] ?? 0);
            $pending = (float) $total - $paid;
            if ($pending > 0.01) {
                $result[] = [
                    'waiter_id' => (int) $waiterId,
                    'pending' => round($pending, 2),
                ];
            }
        }

        return response()->json(['data' => $result]);
    }
}
