<?php

namespace Modules\Orders\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\ValueObjects\TableStatus;

/**
 * Cuando un pedido se confirma, la mesa pasa a occupied.
 * Solo aplica para pedidos dine_in con table_id.
 */
class UpdateTableOnConfirm
{
    public function handle(OrderConfirmed $event): void
    {
        $order = $event->order;

        // Solo procesar pedidos con mesa asociada
        if (!$order->table_id) {
            return;
        }

        $table = RestaurantTable::find($order->table_id);

        if (!$table) {
            Log::warning('UpdateTableOnConfirm: Mesa no encontrada', [
                'order_id' => $order->id,
                'table_id' => $order->table_id,
            ]);
            return;
        }

        // Solo ocupar si la mesa está disponible
        if ($table->status !== TableStatus::Available) {
            Log::warning('UpdateTableOnConfirm: Mesa no está disponible', [
                'order_id' => $order->id,
                'table_id' => $table->id,
                'current_status' => $table->status->value,
            ]);
            return;
        }

        $table->status = TableStatus::Occupied;
        $table->current_order_id = $order->id;
        $table->save();

        Log::info('UpdateTableOnConfirm: Mesa ocupada', [
            'order_id' => $order->id,
            'table_id' => $table->id,
        ]);
    }
}
