<?php

namespace Modules\Recipes\Domain\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Events\OrderCancelled;
use Modules\Recipes\Domain\Entities\ProductRecipe;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\Entities\RawIngredientMovement;
use Modules\Recipes\Domain\ValueObjects\MovementType;

/**
 * ADR-022: Listener que revierte la deducción de insumos cuando un pedido
 * confirmado es cancelado posteriormente.
 *
 * Reemplaza al antiguo ReturnStockOnOrderCancel del módulo Inventory (eliminado).
 *
 * Comportamiento:
 * - Busca los movimientos OutConsumption previos (referencia a la orden)
 * - Crea un movimiento InProduction (reversión) con la misma cantidad
 * - Actualiza el stock del ingrediente
 * - Si no encuentra movimientos previos (ej: producto sin receta), no hace nada
 */
class ReturnIngredientsOnOrderCancel
{
    public function handle(OrderCancelled $event): void
    {
        $order = $event->order;

        Log::info('ReturnIngredientsOnOrderCancel: procesando cancelación', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);

        try {
            DB::transaction(function () use ($order) {
                foreach ($order->items as $orderItem) {
                    $this->returnIngredientsForOrderItem($orderItem, $order);
                }
            });

            Log::info('ReturnIngredientsOnOrderCancel: reversión completada', [
                'order_id' => $order->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ReturnIngredientsOnOrderCancel: error al revertir', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function returnIngredientsForOrderItem($orderItem, Order $order): void
    {
        // Buscar movimientos OutConsumption previos para este order_item
        // (pueden ser varios si la receta tenía múltiples ingredientes)
        $previousMovements = RawIngredientMovement::where('reference_type', 'order')
            ->where('reference_id', $order->id)
            ->where('type', MovementType::OutConsumption)
            ->get();

        if ($previousMovements->isEmpty()) {
            Log::info('No hay movimientos previos para revertir', [
                'order_id' => $order->id,
                'product_id' => $orderItem->product_id,
            ]);
            return;
        }

        // Filtrar solo los movimientos de ESTE order item específico
        // (buscamos los ingredientes de la receta del producto)
        $recipe = ProductRecipe::withoutGlobalScopes()
            ->where('product_id', $orderItem->product_id)
            ->where('company_id', $orderItem->company_id)
            ->first();

        if (!$recipe) {
            return;
        }

        $recipeIngredientIds = $recipe->items->pluck('raw_ingredient_id')->all();

        foreach ($previousMovements as $movement) {
            if (!in_array($movement->raw_ingredient_id, $recipeIngredientIds)) {
                continue;
            }

            $ingredient = RawIngredient::withoutGlobalScopes()
                ->where('id', $movement->raw_ingredient_id)
                ->first();

            if (!$ingredient) {
                continue;
            }

            // Registrar movimiento de reversión (InProduction = stock vuelve)
            RawIngredientMovement::record(
                companyId: $movement->company_id,
                branchId: $movement->branch_id,
                rawIngredientId: $movement->raw_ingredient_id,
                type: MovementType::InProduction,
                quantityBase: (float) $movement->quantity_base,
                referenceType: 'order_cancel',
                referenceId: $order->id,
                reason: "Reversión por cancelación de pedido #{$order->order_number}"
            );
        }
    }
}
