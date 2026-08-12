<?php

namespace Modules\Kitchen\Domain\Services;

use Illuminate\Support\Collection;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;

/**
 * Servicio de cola de cocina.
 * Agrupa pedidos por zona de cocina y calcula estadísticas.
 */
class KitchenQueueService
{
    /**
     * Obtiene la cola de cocina de una sucursal.
     * Retorna pedidos confirmed + preparing agrupados por zona.
     */
    public function getQueue(int $branchId): Collection
    {
        $orders = Order::with(['items.menuItem.product', 'table', 'waiter'])
            ->where('branch_id', $branchId)
            ->inKitchenQueue()
            ->orderByRaw("CASE priority WHEN 'vip' THEN 1 WHEN 'rush' THEN 2 ELSE 3 END ASC")
            ->orderBy('confirmed_at', 'asc')
            ->get();

        return $orders->groupBy(function ($order) {
            // Agrupar por zona de cocina del primer producto
            $firstItem = $order->items->first();
            $product = $firstItem?->menuItem?->product;
            return $product?->kitchen_zone_id ?? 'default';
        });
    }

    /**
     * Obtiene estadísticas de la cocina.
     */
    public function getStats(int $branchId): array
    {
        $baseQuery = Order::where('branch_id', $branchId);

        $confirmed = (clone $baseQuery)->where('status', OrderStatus::CONFIRMED)->count();
        $preparing = (clone $baseQuery)->where('status', OrderStatus::PREPARING)->count();
        $ready = (clone $baseQuery)->where('status', OrderStatus::READY)->count();

        // Tiempo promedio de preparación (última hora)
        $recentOrders = (clone $baseQuery)
            ->whereIn('status', [OrderStatus::READY, OrderStatus::SERVED, OrderStatus::PAID, OrderStatus::CLOSED])
            ->where('confirmed_at', '>=', now()->subHour())
            ->whereNotNull('served_at')
            ->get();

        $avgPrepMinutes = 0;
        if ($recentOrders->isNotEmpty()) {
            $totalMinutes = $recentOrders->sum(function ($order) {
                return $order->confirmed_at->diffInMinutes($order->served_at);
            });
            $avgPrepMinutes = round($totalMinutes / $recentOrders->count(), 1);
        }

        return [
            'confirmed' => $confirmed,
            'preparing' => $preparing,
            'ready' => $ready,
            'total_active' => $confirmed + $preparing + $ready,
            'avg_preparation_minutes' => $avgPrepMinutes,
            'orders_last_hour' => $recentOrders->count(),
        ];
    }

    /**
     * Obtiene el historial de pedidos completados hoy.
     */
    public function getHistory(int $branchId, int $limit = 50): Collection
    {
        return Order::with(['items', 'table', 'waiter'])
            ->where('branch_id', $branchId)
            ->whereIn('status', [OrderStatus::SERVED, OrderStatus::PAID, OrderStatus::CLOSED])
            ->whereDate('updated_at', today())
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
