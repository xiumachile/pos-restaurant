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

        // DEFENSA EN PROFUNDIDAD: Cast explícito a int
        // El modelo ya castea current_order_id a integer, pero forzamos el cast
        // aquí para evitar edge cases con PDO_PGSQL y comparaciones estrictas.
        if ((int) $table->current_order_id !== (int) $order->id) {
            Log::warning('ReleaseTableOnOrderPaid: current_order_id no coincide', [
                'order_id' => $order->id,
                'table_id' => $table->id,
                'table_current_order_id' => $table->current_order_id,
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
