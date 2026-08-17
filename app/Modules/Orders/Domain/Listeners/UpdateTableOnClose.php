<?php

namespace Modules\Orders\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Events\OrderClosed;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\ValueObjects\TableStatus;

/**
 * Cuando un pedido se cierra, la mesa vuelve a available — pero SOLO si no
 * quedan otros pedidos de esa mesa todavía abiertos.
 */
class UpdateTableOnClose
{
    public function handle(OrderClosed $event): void
    {
        $order = $event->order;

        if (!$order->table_id) {
            return;
        }

        $table = RestaurantTable::find($order->table_id);

        if (!$table) {
            return;
        }

        if ($table->status !== TableStatus::Billing) {
            Log::warning('UpdateTableOnClose: Mesa no está en billing, no se libera', [
                'order_id' => $order->id,
                'table_id' => $table->id,
                'current_status' => $table->status->value,
            ]);
            return;
        }

        $hasOpenOrders = Order::where('table_id', $table->id)
            ->whereIn('status', [OrderStatus::CONFIRMED, OrderStatus::PREPARING, OrderStatus::READY, OrderStatus::SERVED])
            ->exists();

        if ($hasOpenOrders) {
            Log::info('UpdateTableOnClose: mesa aún tiene otros pedidos abiertos, no se libera todavía', [
                'order_id' => $order->id,
                'table_id' => $table->id,
            ]);
            return;
        }

        $table->free();
        $table->save();

        Log::info('UpdateTableOnClose: Mesa liberada', [
            'order_id' => $order->id,
            'table_id' => $table->id,
        ]);
    }
}
