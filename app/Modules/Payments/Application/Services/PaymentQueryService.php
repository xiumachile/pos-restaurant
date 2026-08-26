<?php

namespace Modules\Payments\Application\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Payments\Domain\Contracts\PaymentQueryServiceInterface;

/**
 * Implementación del servicio de consultas de pagos.
 * 
 * F1.4a: Esta clase encapsula toda la lógica de consulta a la tabla payments,
 * permitiendo que otros módulos obtengan información sin conocer la estructura interna.
 */
class PaymentQueryService implements PaymentQueryServiceInterface
{
    public function getPaymentsByMethodInSession(int $cashSessionId): Collection
    {
        return DB::table('payments')
            ->where('cash_session_id', $cashSessionId)
            ->where('status', 'completed')
            ->select(
                'method_code',
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('SUM(tip_amount) as total_tips'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('method_code')
            ->get()
            ->keyBy('method_code');
    }

    public function getWaiterTipsInSession(int $cashSessionId, int $waiterId): float
    {
        return (float) DB::table('payments')
            ->where('payments.cash_session_id', $cashSessionId)
            ->where('payments.status', 'completed')
            ->where('payments.tip_amount', '>', 0)
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->where('orders.waiter_id', $waiterId)
            ->sum('payments.tip_amount');
    }

    public function getTipsByMethodInSession(int $cashSessionId): Collection
    {
        return DB::table('payments')
            ->where('cash_session_id', $cashSessionId)
            ->where('status', 'completed')
            ->where('tip_amount', '>', 0)
            ->select(
                'method_code',
                DB::raw('SUM(tip_amount) as total_tips'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('method_code')
            ->get()
            ->keyBy('method_code');
    }

    public function getTipsByWaiterAndMethod(int $cashSessionId): Collection
    {
        $payments = DB::table('payments')
            ->where('payments.cash_session_id', $cashSessionId)
            ->where('payments.status', 'completed')
            ->where('payments.tip_amount', '>', 0)
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->whereNotNull('orders.waiter_id')
            ->select('orders.waiter_id', 'payments.method_code', 'payments.tip_amount')
            ->get();

        return $payments->groupBy('waiter_id')
            ->map(function ($group) {
                return $group->groupBy('method_code')
                    ->map(fn($items) => (float) $items->sum('tip_amount'));
            });
    }

    public function getDailyPaymentsByMethod(int $branchId, string $dateStart, string $dateEnd): Collection
    {
        return DB::table('payments')
            ->join('cash_sessions', 'payments.cash_session_id', '=', 'cash_sessions.id')
            ->where('cash_sessions.branch_id', $branchId)
            ->whereBetween('payments.paid_at', [$dateStart, $dateEnd])
            ->where('payments.status', 'completed')
            ->select(
                'payments.method_code',
                DB::raw('SUM(payments.amount) as total_amount'),
                DB::raw('SUM(payments.tip_amount) as total_tips'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('payments.method_code')
            ->get()
            ->keyBy('method_code');
    }

    public function getAllPaymentsInSession(int $cashSessionId): Collection
    {
        return DB::table('payments')
            ->where('cash_session_id', $cashSessionId)
            ->where('status', 'completed')
            ->get();
    }
}
