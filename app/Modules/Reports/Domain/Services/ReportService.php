<?php

namespace Modules\Reports\Domain\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportService
{
    /**
     * KPIs del dashboard (hoy)
     */
    public function getDashboardKPIs(int $companyId, int $branchId): array
    {
        $today = Carbon::today();

        $ordersToday = DB::table('orders')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereIn('status', ['paid', 'confirmed'])
            ->whereDate('paid_at', $today)
            ->get();

        $todaySales = $ordersToday->sum('total');
        $todayOrdersCount = $ordersToday->count();
        $averageTicket = $todayOrdersCount > 0
            ? (int) round($todaySales / $todayOrdersCount)
            : 0;

        $todayTips = DB::table('payments')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('status', 'completed')
            ->whereDate('paid_at', $today)
            ->sum('tip_amount');

        return [
            'today_sales' => (int) $todaySales,
            'today_orders_count' => $todayOrdersCount,
            'average_ticket' => $averageTicket,
            'today_tips' => (int) $todayTips,
        ];
    }

    /**
     * Top productos vendidos en los últimos N días
     */
    public function getTopProducts(int $companyId, int $branchId, int $days = 7, int $limit = 10): array
    {
        $since = Carbon::now()->subDays($days);

        return DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('o.company_id', $companyId)
            ->where('o.branch_id', $branchId)
            ->whereNotIn('o.status', ['cancelled', 'draft'])
            ->whereNotNull('o.paid_at')
            ->where('o.paid_at', '>=', $since)
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
     * Ventas por hora del día (distribución horaria)
     */
    public function getSalesByHour(int $companyId, int $branchId, int $days = 7): array
    {
        $since = Carbon::now()->subDays($days);

        $rows = DB::table('orders')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereIn('status', ['paid', 'confirmed'])
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $since)
            ->select(
                DB::raw('EXTRACT(HOUR FROM paid_at) as hour'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(total) as total')
            )
            ->groupByRaw('EXTRACT(HOUR FROM paid_at)')
            ->orderByRaw('EXTRACT(HOUR FROM paid_at)')
            ->get();

        // Rellenar horas sin ventas (0-23)
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
     * Distribución por método de pago
     */
    public function getPaymentMethods(int $companyId, int $branchId, int $days = 30): array
    {
        $since = Carbon::now()->subDays($days);

        return DB::table('payments')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('status', 'completed')
            ->where('paid_at', '>=', $since)
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
