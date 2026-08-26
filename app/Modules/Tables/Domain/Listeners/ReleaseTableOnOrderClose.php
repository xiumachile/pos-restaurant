<?php

namespace Modules\Tables\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Events\OrderClosed;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\Events\TableReleased;

class ReleaseTableOnOrderClose
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

        if ($table->current_order_id !== $order->id) {
            return;
        }

        $table->status = \Modules\Tables\Domain\ValueObjects\TableStatus::Available;
        $table->current_order_id = null;
        $table->save();

        event(new TableReleased($table, $order));

        Log::info('ReleaseTableOnOrderClose: Mesa liberada', [
            'order_id' => $order->id,
            'table_id' => $table->id,
        ]);
    }
}
