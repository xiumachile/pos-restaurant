<?php

namespace Modules\Tables\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\Events\TableOccupied;
use Modules\Tables\Domain\Exceptions\InvalidTableStatusTransition;

/**
 * Cuando un pedido se confirma, la mesa pasa a occupied.
 * Solo aplica para pedidos dine_in con table_id.
 *
 * REFACTOR F1.2a: Movido desde Orders para respetar encapsulamiento.
 * Ahora Tables escucha eventos de Orders y modifica SU PROPIO estado.
 */
class OccupyTableOnOrderConfirm
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
            Log::warning('OccupyTableOnOrderConfirm: Mesa no encontrada', [
                'order_id' => $order->id,
                'table_id' => $order->table_id,
            ]);
            return;
        }

        try {
            // Usar la máquina de estados en vez de asignar directamente
            $table->occupy($order->id);
            $table->save();

            // Emitir evento de dominio para que otros módulos reaccionen
            event(new TableOccupied($table, $order));

            Log::info('OccupyTableOnOrderConfirm: Mesa ocupada', [
                'order_id' => $order->id,
                'table_id' => $table->id,
            ]);
        } catch (InvalidTableStatusTransition $e) {
            Log::warning('OccupyTableOnOrderConfirm: Transición inválida', [
                'order_id' => $order->id,
                'table_id' => $table->id,
                'current_status' => $table->status->value,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
