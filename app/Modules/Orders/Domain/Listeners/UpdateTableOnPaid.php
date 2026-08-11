<?php

namespace Modules\Orders\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\ValueObjects\TableStatus;

/**
 * Cuando un pedido se paga, la mesa pasa a billing.
 * Esto indica que la mesa está en proceso de cierre.
 */
class UpdateTableOnPaid
{
    public function handle(OrderPaid $event): void
    {
        $order = $event->order;

        if (!$order->table_id) {
            return;
        }

        $table = RestaurantTable::find($order->table_id);

        if (!$table) {
            return;
        }

        // Solo mover a billing si está ocupada por este pedido
        if ($table->status !== TableStatus::Occupied || $table->current_order_id !== $order->id) {
            Log::warning('UpdateTableOnPaid: Mesa no está ocupada por este pedido', [
                'order_id' => $order->id,
                'table_id' => $table->id,
                'current_status' => $table->status->value,
                'current_order_id' => $table->current_order_id,
            ]);
            return;
        }

        $table->status = TableStatus::Billing;
        $table->save();

        Log::info('UpdateTableOnPaid: Mesa en billing', [
            'order_id' => $order->id,
            'table_id' => $table->id,
        ]);
    }
}
