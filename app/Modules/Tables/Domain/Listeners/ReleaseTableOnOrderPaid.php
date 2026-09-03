<?php

namespace Modules\Tables\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\Events\TableReleased;

class ReleaseTableOnOrderPaid
{
    public function handle(OrderPaid $event): void
    {
        $order = $event->order;

        if (!$order->table_id) {
            Log::warning('ReleaseTableOnOrderPaid: order sin table_id', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
            return;
        }

        $table = RestaurantTable::find($order->table_id);

        if (!$table) {
            Log::warning('ReleaseTableOnOrderPaid: mesa no encontrada', [
                'order_id' => $order->id,
                'table_id' => $order->table_id,
            ]);
            return;
        }

        if ($table->current_order_id !== $order->id) {
            Log::warning('ReleaseTableOnOrderPaid: current_order_id no coincide (posible type mismatch)', [
                'order_id' => $order->id,
                'order_id_type' => gettype($order->id),
                'table_id' => $table->id,
                'table_current_order_id' => $table->current_order_id,
                'table_current_order_id_type' => gettype($table->current_order_id),
                'strict_compare_result' => ($table->current_order_id !== $order->id),
                'loose_compare_result' => ($table->current_order_id != $order->id),
            ]);
            return;
        }

        $table->status = \Modules\Tables\Domain\ValueObjects\TableStatus::Available;
        $table->current_order_id = null;
        $table->save();

        event(new TableReleased($table, $order));

        Log::info('ReleaseTableOnOrderPaid: Mesa liberada', [
            'order_id' => $order->id,
            'table_id' => $table->id,
        ]);
    }
}
