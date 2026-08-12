<?php

namespace Modules\Orders\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Events\OrderClosed;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\ValueObjects\TableStatus;

/**
 * Cuando un pedido se cierra, la mesa vuelve a available.
 * Se libera el current_order_id.
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

        // Solo liberar si está en billing con este pedido
        if ($table->status !== TableStatus::Billing || $table->current_order_id !== $order->id) {
            Log::warning('UpdateTableOnClose: Mesa no está en billing con este pedido', [
                'order_id' => $order->id,
                'table_id' => $table->id,
            ]);
            return;
        }

        $table->status = TableStatus::Available;
        $table->current_order_id = null;
        $table->save();

        Log::info('UpdateTableOnClose: Mesa liberada', [
            'order_id' => $order->id,
            'table_id' => $table->id,
        ]);
    }
}
