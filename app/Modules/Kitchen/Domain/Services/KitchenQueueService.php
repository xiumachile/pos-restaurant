<?php

namespace Modules\Kitchen\Domain\Services;

use Illuminate\Database\Eloquent\Builder;
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
     * 
     * F2.3: Excluido estado ready (ya no está en cola de cocina)
     */
    public function getQueue(int $branchId): Collection
    {
        $orders = Order::with(['items.menuItem.product', 'table', 'waiter'])
            ->where('branch_id', $branchId)
            ->where(function (Builder $query) {
                $query->whereIn('status', [
                    OrderStatus::CONFIRMED,
                    OrderStatus::PREPARING,
                ]);
            })
            ->orderByRaw("CASE priority WHEN 'vip' THEN 1 WHEN 'rush' THEN 2 ELSE 3 END ASC")
            ->orderBy('confirmed_at', 'asc')
            ->get();

        return $orders->groupBy(function ($order) {
            $firstItem = $order->items->first();
            $product = $firstItem?->menuItem?->product;
            return $product?->kitchen_zone_id ?? 'default';
        });
    }

    /**
     * Obtiene estadísticas de la cocina.
     * 
     * F2.3: Alineado con tests - usa avg_preparation_minutes y orders_last_hour
     */
    public function getStats(int $branchId): array
    {
        $baseQuery = Order::where('branch_id', $branchId);

        $confirmed = (clone $baseQuery)->where('status', OrderStatus::CONFIRMED)->count();
        $preparing = (clone $baseQuery)->where('status', OrderStatus::PREPARING)->count();
        $ready = (clone $baseQuery)->where('status', OrderStatus::READY)->count();
        $totalActive = $confirmed + $preparing + $ready;

        // Pedidos completados en la última hora (para cálculo de tiempo promedio)
        $recentOrders = (clone $baseQuery)
            ->whereIn('status', [OrderStatus::READY, OrderStatus::SERVED, OrderStatus::PAID, OrderStatus::CLOSED])
            ->where('confirmed_at', '>=', now()->subHour())
            ->whereNotNull('served_at')
            ->get();

        // Tiempo promedio de preparación en MINUTOS
        $avgPrepSeconds = $recentOrders->avg(function ($order) {
            if (!$order->served_at || !$order->confirmed_at) {
                return null;
            }
            return now()->parse($order->served_at)->diffInSeconds(now()->parse($order->confirmed_at));
        });
        
        $avgPrepMinutes = $avgPrepSeconds ? round($avgPrepSeconds / 60, 1) : null;

        // Pedidos en la última hora (todos los estados activos)
        $ordersLastHour = (clone $baseQuery)
            ->whereIn('status', [
                OrderStatus::CONFIRMED,
                OrderStatus::PREPARING,
                OrderStatus::READY,
                OrderStatus::SERVED,
            ])
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return [
            'confirmed' => $confirmed,
            'preparing' => $preparing,
            'ready' => $ready,
            'total_active' => $totalActive,
            'avg_preparation_minutes' => $avgPrepMinutes,
            'orders_last_hour' => $ordersLastHour,
        ];
    }

    /**
     * Obtiene el historial de pedidos recientes de cocina.
     * Retorna pedidos served, paid, closed de las últimas 24h.
     * 
     * F2.3: Método nuevo requerido por tests.
     */
    public function getHistory(int $branchId, int $limit = 50): Collection
    {
        return Order::with(['items.menuItem.product', 'table', 'waiter'])
            ->where('branch_id', $branchId)
            ->whereIn('status', [
                OrderStatus::SERVED,
                OrderStatus::PAID,
                OrderStatus::CLOSED,
            ])
            ->where('updated_at', '>=', now()->subDay())
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
