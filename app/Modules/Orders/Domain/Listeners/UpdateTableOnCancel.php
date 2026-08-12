<?php

namespace Modules\Orders\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Events\OrderCancelled;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\ValueObjects\TableStatus;

/**
 * Cuando un pedido se cancela, la mesa vuelve a available si estaba ocupada.
 */
class UpdateTableOnCancel
{
    public function handle(OrderCancelled $event): void
    {
        $order = $event->order;

        if (!$order->table_id) {
            return;
        }

        $table = RestaurantTable::find($order->table_id);

        if (!$table) {
            return;
        }

        // Solo liberar si está ocupada por este pedido
        if ($table->status !== TableStatus::Occupied || $table->current_order_id !== $order->id) {
            return;
        }

        $table->status = TableStatus::Available;
        $table->current_order_id = null;
        $table->save();

        Log::info('UpdateTableOnCancel: Mesa liberada por cancelación', [
            'order_id' => $order->id,
            'table_id' => $table->id,
        ]);
    }
}
