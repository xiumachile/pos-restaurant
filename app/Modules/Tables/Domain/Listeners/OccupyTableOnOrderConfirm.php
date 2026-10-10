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
 * MEJORA F1.3: Permite reasignar mesa ocupada (occupied → occupied)
 * cuando una nueva orden se confirma en una mesa que ya está ocupada
 * por una orden anterior (edge case: múltiples pedidos en la misma mesa).
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

        // IDEMPOTENCIA: si la mesa ya está ocupada con ESTA orden, no hacer nada
        if ($table->current_order_id === $order->id && $table->status->value === 'occupied') {
            Log::info('OccupyTableOnOrderConfirm: Mesa ya ocupada con esta orden (idempotencia)', [
                'order_id' => $order->id,
                'table_id' => $table->id,
            ]);
            return;
        }

        try {
            // Si la mesa está available, hacer transición normal
            if ($table->status->value === 'available') {
                $table->occupy($order->id);
                $table->save();
                
                event(new TableOccupied($table, $order));

                Log::info('OccupyTableOnOrderConfirm: Mesa ocupada (transición normal)', [
                    'order_id' => $order->id,
                    'table_id' => $table->id,
                    'previous_order_id' => null,
                ]);
                return;
            }

            // Si la mesa ya está occupied, actualizar current_order_id directamente
            // (caso: mesa con orden antigua que ahora tiene orden nueva)
            if ($table->status->value === 'occupied') {
                $previousOrderId = $table->current_order_id;
                $table->current_order_id = $order->id;
                $table->save();

                Log::info('OccupyTableOnOrderConfirm: Mesa reasignada a nueva orden', [
                    'order_id' => $order->id,
                    'table_id' => $table->id,
                    'previous_order_id' => $previousOrderId,
                ]);
                return;
            }

            // Para otros estados (reserved, maintenance), usar transición normal
            $table->occupy($order->id);
            $table->save();
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
