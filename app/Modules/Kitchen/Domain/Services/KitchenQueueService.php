<?php

namespace Modules\Kitchen\Domain\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderPriority;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Tables\Domain\Entities\RestaurantTable;

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
     */
    public function getStats(int $branchId): array
    {
        $baseQuery = Order::where('branch_id', $branchId);

        $confirmed = (clone $baseQuery)->where('status', OrderStatus::CONFIRMED)->count();
        $preparing = (clone $baseQuery)->where('status', OrderStatus::PREPARING)->count();
        $ready = (clone $baseQuery)->where('status', OrderStatus::READY)->count();
        $totalActive = $confirmed + $preparing + $ready;

        $recentOrders = (clone $baseQuery)
            ->whereIn('status', [OrderStatus::READY, OrderStatus::SERVED, OrderStatus::PAID, OrderStatus::CLOSED])
            ->where('confirmed_at', '>=', now()->subHour())
            ->whereNotNull('served_at')
            ->get();

        $avgPrepSeconds = $recentOrders->avg(function ($order) {
            if (!$order->served_at || !$order->confirmed_at) {
                return null;
            }
            return now()->parse($order->served_at)->diffInSeconds(now()->parse($order->confirmed_at));
        });
        
        $avgPrepMinutes = $avgPrepSeconds ? round($avgPrepSeconds / 60, 1) : null;

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

    /**
     * Obtiene el historial de pedidos de una mesa específica del día actual.
     */
    public function getTableHistory(int $branchId, string $tableUuid): array
    {
        $table = RestaurantTable::where('uuid', $tableUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $orders = Order::with(['items.menuItem.product', 'waiter'])
            ->where('table_id', $table->id)
            ->where('branch_id', $branchId)
            ->whereDate('created_at', today())
            ->orderBy('created_at', 'asc')
            ->get();

        return [
            'table' => [
                'uuid' => $table->uuid,
                'table_number' => $table->table_number,
                'area_code' => $table->area_code,
                'capacity' => $table->capacity,
            ],
            'orders' => $orders,
            'summary' => [
                'total_orders' => $orders->count(),
                'total_items' => $orders->sum(fn($o) => $o->items->sum('quantity')),
                'total_amount' => (float) $orders->sum('total'),
                'first_order_at' => $orders->first()?->created_at?->toIso8601String(),
                'last_order_at' => $orders->last()?->created_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Obtiene todas las mesas con actividad del día actual.
     */
    public function getTablesToday(int $branchId): Collection
    {
        $tableIds = Order::where('branch_id', $branchId)
            ->whereDate('created_at', today())
            ->whereNotNull('table_id')
            ->distinct()
            ->pluck('table_id');

        $tables = RestaurantTable::with(['orders' => function ($query) {
            $query->whereDate('created_at', today())
                ->with(['items'])
                ->orderBy('created_at', 'asc');
        }])
            ->whereIn('id', $tableIds)
            ->orderBy('area_code')
            ->orderBy('table_number')
            ->get();

        return $tables->map(function ($table) {
            $orders = $table->orders;
            $totalAmount = $orders->sum('total');
            $totalItems = $orders->sum(fn($o) => $o->items->sum('quantity'));
            $lastOrderStatus = $orders->last()?->status?->value;
            
            return [
                'uuid' => $table->uuid,
                'table_number' => $table->table_number,
                'area_code' => $table->area_code,
                'capacity' => $table->capacity,
                'orders_count' => $orders->count(),
                'total_items' => $totalItems,
                'total_amount' => (float) $totalAmount,
                'last_order_status' => $lastOrderStatus,
                'first_order_at' => $orders->first()?->created_at?->toIso8601String(),
                'last_order_at' => $orders->last()?->created_at?->toIso8601String(),
            ];
        });
    }

    /**
     * Asigna un cocinero a un pedido.
     * 
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function assignCookToOrder(string $orderUuid, string $cookUuid, int $companyId): Order
    {
        $order = Order::where('uuid', $orderUuid)
            ->where('company_id', $companyId)
            ->firstOrFail();

        if (!$order->status->isInKitchenQueue()) {
            abort(422, 'Solo se pueden asignar cocineros a pedidos en cola de cocina.');
        }

        $cook = User::where('uuid', $cookUuid)
            ->where('role', 'kitchen')
            ->where('company_id', $companyId)
            ->firstOrFail();

        $order->assigned_cook_id = $cook->id;
        $order->save();

        $order->load(['items', 'table', 'waiter', 'assignedCook']);

        return $order;
    }

    /**
     * Actualiza la prioridad de un pedido.
     * 
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function updateOrderPriority(string $orderUuid, string $priority, int $companyId): Order
    {
        $order = Order::where('uuid', $orderUuid)
            ->where('company_id', $companyId)
            ->firstOrFail();

        if ($order->status->isFinalState()) {
            abort(422, 'No se puede cambiar la prioridad de un pedido en estado final.');
        }

        $order->priority = OrderPriority::from($priority);
        $order->save();

        $order->load(['items', 'table', 'waiter', 'assignedCook']);

        return $order;
    }
}
