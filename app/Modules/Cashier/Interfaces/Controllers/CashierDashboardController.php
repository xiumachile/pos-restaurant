<?php

namespace Modules\Cashier\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Cashier\Domain\Entities\CashCount;
use Modules\Cashier\Domain\Entities\CashMovement;
use Modules\Cashier\Domain\Entities\CashRegister;
use Modules\Cashier\Domain\ValueObjects\MovementType;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;

class CashierDashboardController extends Controller
{
    /**
     * GET /api/v1/cashier/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $branchId = $user->branch_id;

        // Sesión activa
        $openSession = CashSession::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('status', CashSessionStatus::OPEN)
            ->with(['user', 'register'])
            ->first();

        $sessionData = null;
        if ($openSession) {
            $movementsSummary = $openSession->movements()->get();
            
            // Desglose de pagos por método (solo de la sesión actual)
            $paymentsByMethod = DB::table('payments')
                ->where('cash_session_id', $openSession->id)
                ->where('status', 'completed')
                ->select('method_code', 
                    DB::raw('SUM(amount) as total_amount'),
                    DB::raw('SUM(tip_amount) as total_tips'),
                    DB::raw('COUNT(*) as count'))
                ->groupBy('method_code')
                ->get()
                ->keyBy('method_code');

            $breakdown = [
                'cash' => [
                    'amount' => (float) ($paymentsByMethod['CASH']->total_amount ?? 0),
                    'tips' => (float) ($paymentsByMethod['CASH']->total_tips ?? 0),
                    'count' => (int) ($paymentsByMethod['CASH']->count ?? 0),
                ],
                'card' => [
                    'amount' => (float) ($paymentsByMethod['CARD']->total_amount ?? 0),
                    'tips' => (float) ($paymentsByMethod['CARD']->total_tips ?? 0),
                    'count' => (int) ($paymentsByMethod['CARD']->count ?? 0),
                ],
                'transfer' => [
                    'amount' => (float) ($paymentsByMethod['TRANSFER']->total_amount ?? 0),
                    'tips' => (float) ($paymentsByMethod['TRANSFER']->total_tips ?? 0),
                    'count' => (int) ($paymentsByMethod['TRANSFER']->count ?? 0),
                ],
                'gift_card' => [
                    'amount' => (float) ($paymentsByMethod['GIFT_CARD']->total_amount ?? 0),
                    'tips' => (float) ($paymentsByMethod['GIFT_CARD']->total_tips ?? 0),
                    'count' => (int) ($paymentsByMethod['GIFT_CARD']->count ?? 0),
                ],
            ];

            // Calcular totales
            $totalSalesAmount = array_sum(array_column($breakdown, 'amount'));
            $totalTips = array_sum(array_column($breakdown, 'tips'));
            $totalTransactions = array_sum(array_column($breakdown, 'count'));
            
            // EXPECTED BRUTO: inicial + ventas efectivo + propinas efectivo
            // Este es el monto MÁXIMO que puede haber en caja (si no se han entregado propinas)
            // El wizard descontará las propinas al momento del cierre según si fueron entregadas o no
            $totalCashExpected = (float) $openSession->opening_amount 
                + $breakdown['cash']['amount'] 
                + $breakdown['cash']['tips'];

            $sessionData = [
                'uuid' => $openSession->uuid,
                'session_number' => $openSession->session_number,
                'user_name' => $openSession->user?->name,
                'register_name' => $openSession->register?->name,
                'opening_amount' => (float) $openSession->opening_amount,
                'breakdown' => $breakdown,
                'total_sales_amount' => $totalSalesAmount,
                'total_tips' => $totalTips,
                'total_transactions' => $totalTransactions,
                'total_cash_expected' => $totalCashExpected,
                'total_grand_expected' => $totalSalesAmount + $totalTips,
                'current_balance' => $openSession->calculateCurrentBalance(),
                'expected_amount' => $openSession->calculateExpectedAmount(),
                'opened_at' => $openSession->opened_at?->toIso8601String(),
                'hours_open' => now()->diffInHours($openSession->opened_at, false),
                'withdrawals_total' => (float) $movementsSummary
                    ->where('type', MovementType::WITHDRAWAL)
                    ->sum('amount'),
                'deposits_total' => (float) $movementsSummary
                    ->where('type', MovementType::DEPOSIT)
                    ->sum('amount'),
                'movements_count' => $movementsSummary->count(),
            ];
        }

        // Cajas registradoras
        $registers = CashRegister::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->active()
            ->get();

        $registersData = $registers->map(function ($register) {
            return [
                'uuid' => $register->uuid,
                'name' => $register->name,
                'code' => $register->code,
                'is_available' => $register->isAvailable(),
                'is_busy' => $register->isBusy(),
                'current_session_uuid' => $register->currentSession()?->uuid,
            ];
        });

        // Estadísticas del día
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $todaySessions = CashSession::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereBetween('opened_at', [$todayStart, $todayEnd])
            ->get();

        $todayCounts = CashCount::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->get();

        $todayMovements = CashMovement::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->get();

        // Estadísticas de pagos del día (todas las sesiones)
        $todayPayments = DB::table('payments')
            ->join('cash_sessions', 'payments.cash_session_id', '=', 'cash_sessions.id')
            ->where('cash_sessions.branch_id', $branchId)
            ->where('payments.status', 'completed')
            ->whereBetween('payments.paid_at', [$todayStart, $todayEnd])
            ->select('method_code',
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('SUM(tip_amount) as total_tips'),
                DB::raw('COUNT(*) as count'))
            ->groupBy('method_code')
            ->get()
            ->keyBy('method_code');

        $stats = [
            'sessions_today' => $todaySessions->count(),
            'sessions_open' => $todaySessions->where('status', CashSessionStatus::OPEN)->count(),
            'sessions_closed' => $todaySessions->where('status', CashSessionStatus::CLOSED)->count(),
            'counts_today' => $todayCounts->count(),
            'discrepant_counts_today' => $todayCounts->where('has_discrepancy', true)->count(),
            'movements_today' => $todayMovements->count(),
            'total_withdrawals_today' => (float) $todayMovements
                ->where('type', MovementType::WITHDRAWAL)
                ->sum('amount'),
            'total_deposits_today' => (float) $todayMovements
                ->where('type', MovementType::DEPOSIT)
                ->sum('amount'),
            'payments_today' => [
                'cash' => (float) ($todayPayments['CASH']->total_amount ?? 0),
                'card' => (float) ($todayPayments['CARD']->total_amount ?? 0),
                'transfer' => (float) ($todayPayments['TRANSFER']->total_amount ?? 0),
                'gift_card' => (float) ($todayPayments['GIFT_CARD']->total_amount ?? 0),
                'tips' => (float) ($todayPayments->sum('total_tips')),
                'total' => (float) ($todayPayments->sum('total_amount') + $todayPayments->sum('total_tips')),
            ],
        ];

        return response()->json([
            'data' => [
                'current_session' => $sessionData,
                'registers' => $registersData,
                'statistics_today' => $stats,
            ],
        ]);
    }

    /**
     * GET /api/v1/cashier/session-payments
     * Lista pagos completados de la sesión de caja abierta con detalle.
     */
    public function sessionPayments(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $user->branch_id;

        $openSession = CashSession::where('company_id', $user->company_id)
            ->where('branch_id', $branchId)
            ->where('status', CashSessionStatus::OPEN)
            ->first();

        if (!$openSession) {
            return response()->json([
                'data' => [
                    'session' => null,
                    'payments' => [],
                    'summary' => null,
                ],
            ]);
        }

        $payments = DB::table('payments')
            ->leftJoin('orders', 'payments.order_id', '=', 'orders.id')
            ->leftJoin('restaurant_tables', 'orders.table_id', '=', 'restaurant_tables.id')
            ->leftJoin('users', 'payments.user_id', '=', 'users.id')
            ->where('payments.cash_session_id', $openSession->id)
            ->where('payments.status', 'completed')
            ->orderBy('payments.paid_at', 'desc')
            ->select(
                'payments.uuid',
                'payments.payment_number',
                'payments.method_code',
                'payments.amount',
                'payments.tip_amount',
                'payments.total_amount',
                'payments.reference_code',
                'payments.paid_at',
                'orders.order_number',
                'restaurant_tables.table_number',
                'users.name as cashier_name'
            )
            ->get()
            ->map(function ($p) {
                return [
                    'uuid' => $p->uuid,
                    'payment_number' => $p->payment_number,
                    'method_code' => $p->method_code,
                    'amount' => (float) $p->amount,
                    'tip_amount' => (float) $p->tip_amount,
                    'total_amount' => (float) $p->total_amount,
                    'reference_code' => $p->reference_code,
                    'paid_at' => $p->paid_at,
                    'order_number' => $p->order_number,
                    'table_number' => $p->table_number,
                    'cashier_name' => $p->cashier_name,
                ];
            });

        // Resumen por método
        $summary = [
            'cash' => ['amount' => 0.0, 'tips' => 0.0, 'count' => 0],
            'card' => ['amount' => 0.0, 'tips' => 0.0, 'count' => 0],
            'transfer' => ['amount' => 0.0, 'tips' => 0.0, 'count' => 0],
            'gift_card' => ['amount' => 0.0, 'tips' => 0.0, 'count' => 0],
        ];

        foreach ($payments as $p) {
            $key = match ($p['method_code']) {
                'CASH' => 'cash',
                'CARD' => 'card',
                'TRANSFER' => 'transfer',
                'GIFT_CARD' => 'gift_card',
                default => null,
            };
            if ($key) {
                $summary[$key]['amount'] += $p['amount'];
                $summary[$key]['tips'] += $p['tip_amount'];
                $summary[$key]['count']++;
            }
        }

        $totalSales = array_sum(array_column($summary, 'amount'));
        $totalTips = array_sum(array_column($summary, 'tips'));

        return response()->json([
            'data' => [
                'session' => [
                    'uuid' => $openSession->uuid,
                    'session_number' => $openSession->session_number,
                    'opening_amount' => (float) $openSession->opening_amount,
                    'opened_at' => $openSession->opened_at?->toIso8601String(),
                    'user_name' => $openSession->user?->name,
                ],
                'payments' => $payments,
                'summary' => [
                    'by_method' => $summary,
                    'total_sales' => $totalSales,
                    'total_tips' => $totalTips,
                    'total_grand' => $totalSales + $totalTips,
                    'total_cash_expected' => (float) $openSession->opening_amount
                        + $summary['cash']['amount'] + $summary['cash']['tips'],
                    'transactions_count' => $payments->count(),
                ],
            ],
        ]);
    }
}
