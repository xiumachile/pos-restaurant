<?php

namespace Modules\Orders\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\ValueObjects\TableStatus;

/**
 * Cuando un pedido se paga, la mesa pasa a billing.
 *
 * IMPORTANTE: una mesa puede tener varios pedidos servidos pagándose en el
 * mismo "cobro por mesa". Este listener se dispara una vez por cada pedido
 * pagado, así que debe ser IDEMPOTENTE: si la mesa ya está en billing,
 * no hace nada — no es un error.
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

        if ($table->status === TableStatus::Billing) {
            return;
        }

        if ($table->status !== TableStatus::Occupied) {
            Log::warning('UpdateTableOnPaid: Mesa no está ocupada, no se puede mover a billing', [
                'order_id' => $order->id,
                'table_id' => $table->id,
                'current_status' => $table->status->value,
            ]);
            return;
        }

        $table->requestBilling();
        $table->save();

        Log::info('UpdateTableOnPaid: Mesa en billing', [
            'order_id' => $order->id,
            'table_id' => $table->id,
        ]);
    }
}
