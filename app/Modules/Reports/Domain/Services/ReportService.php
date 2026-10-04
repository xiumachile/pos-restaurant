<?php

namespace Modules\Reports\Domain\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportService
{
    /**
     * KPIs del dashboard con rango de fechas
     */
    public function getDashboardKPIs(int $companyId, int $branchId, Carbon $from, Carbon $to): array
    {
        $ordersInRange = DB::table('orders')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereIn('status', ['paid', 'confirmed'])
            ->whereBetween('paid_at', [$from, $to])
            ->get();

        $sales = $ordersInRange->sum('total');
        $ordersCount = $ordersInRange->count();
        $averageTicket = $ordersCount > 0
            ? (int) round($sales / $ordersCount)
            : 0;

        $tips = DB::table('payments')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('tip_amount');

        return [
            'today_sales' => (int) $sales,
            'today_orders_count' => $ordersCount,
            'average_ticket' => $averageTicket,
            'today_tips' => (int) $tips,
        ];
    }

    /**
     * Top productos vendidos en rango de fechas
     */
    public function getTopProducts(int $companyId, int $branchId, Carbon $from, Carbon $to, int $limit = 10): array
    {
        return DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('o.company_id', $companyId)
            ->where('o.branch_id', $branchId)
            ->whereNotIn('o.status', ['cancelled', 'draft'])
            ->whereNotNull('o.paid_at')
            ->whereBetween('o.paid_at', [$from, $to])
            ->select(
                'oi.name_snapshot as name',
                DB::raw('SUM(oi.quantity) as quantity'),
                DB::raw('SUM(oi.subtotal) as revenue')
            )
            ->groupBy('oi.name_snapshot')
            ->orderByDesc('quantity')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'name' => $row->name,
                    'quantity' => (int) $row->quantity,
                    'revenue' => (int) $row->revenue,
                ];
            })
            ->toArray();
    }

    /**
     * Ventas por hora del día en rango de fechas
     */
    public function getSalesByHour(int $companyId, int $branchId, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('orders')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereIn('status', ['paid', 'confirmed'])
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to])
            ->select(
                DB::raw('EXTRACT(HOUR FROM paid_at) as hour'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(total) as total')
            )
            ->groupByRaw('EXTRACT(HOUR FROM paid_at)')
            ->orderByRaw('EXTRACT(HOUR FROM paid_at)')
            ->get();

        $hourly = [];
        $dataMap = $rows->keyBy('hour');

        for ($h = 0; $h < 24; $h++) {
            $row = $dataMap->get($h);
            $hourly[] = [
                'hour' => $h,
                'orders_count' => $row ? (int) $row->orders_count : 0,
                'total' => $row ? (int) $row->total : 0,
            ];
        }

        return $hourly;
    }

    /**
     * Distribución por método de pago en rango de fechas
     */
    public function getPaymentMethods(int $companyId, int $branchId, Carbon $from, Carbon $to): array
    {
        return DB::table('payments')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->select(
                'method_code',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_amount) as total_amount')
            )
            ->groupBy('method_code')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($row) {
                return [
                    'method_code' => $row->method_code,
                    'count' => (int) $row->count,
                    'total_amount' => (int) $row->total_amount,
                ];
            })
            ->toArray();
    }
}
