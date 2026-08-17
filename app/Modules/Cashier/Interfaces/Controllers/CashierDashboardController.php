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
            $totalCashExpected = (float) $openSession->opening_amount + $breakdown['cash']['amount'] + $breakdown['cash']['tips'];

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
}
