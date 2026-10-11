<?php

namespace Modules\Recipes\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\Entities\ProductRecipe;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;

/**
 * I-04 FIX: Servicio para calcular la disponibilidad real de stock.
 * 
 * Separa los conceptos de:
 * - Stock físico (current_stock_base)
 * - Stock comprometido (committed_stock_base): pedidos confirmados no servidos
 * - Entradas pendientes (pending_incoming_base): compras offline sin sincronizar
 * - Stock disponible = físico - comprometido + entradas pendientes
 */
class StockAvailabilityService
{
    /**
     * Recalcula el stock comprometido de un ingrediente basándose en
     * pedidos CONFIRMADOS que aún no han sido SERVED/CLOSED/CANCELLED.
     */
    public function recalculateCommittedStock(RawIngredient $ingredient): float
    {
        $activeStatuses = [
            OrderStatus::CONFIRMED,
            OrderStatus::READY,
            OrderStatus::PAID,
        ];

        // Buscar todas las recetas que usan este ingrediente
        $recipes = ProductRecipe::withoutGlobalScopes()
            ->where('company_id', $ingredient->company_id)
            ->whereHas('items', function ($q) use ($ingredient) {
                $q->where('raw_ingredient_id', $ingredient->id);
            })
            ->with(['items' => function ($q) use ($ingredient) {
                $q->where('raw_ingredient_id', $ingredient->id);
            }])
            ->get();

        $totalCommitted = 0.0;

        foreach ($recipes as $recipe) {
            $recipeItem = $recipe->items->first();
            if (!$recipeItem) continue;

            $effectiveQty = (float) $recipeItem->effective_discount_base_quantity;

            // Contar órdenes activas que contienen este producto
            $activeOrders = Order::withoutGlobalScopes()
                ->where('company_id', $ingredient->company_id)
                ->where('branch_id', $ingredient->branch_id)
                ->whereIn('status', $activeStatuses)
                ->whereHas('items', function ($q) use ($recipe) {
                    $q->where('product_id', $recipe->product_id);
                })
                ->with(['items' => function ($q) use ($recipe) {
                    $q->where('product_id', $recipe->product_id);
                }])
                ->get();

            foreach ($activeOrders as $order) {
                foreach ($order->items as $orderItem) {
                    $totalCommitted += $effectiveQty * (float) $orderItem->quantity;
                }
            }
        }

        $ingredient->committed_stock_base = $totalCommitted;
        $ingredient->save();

        Log::info('[ES] Stock comprometido recalculado / [ZH] 已重新计算承诺库存', [
            'ingredient_id' => $ingredient->id,
            'sku' => $ingredient->sku,
            'committed' => $totalCommitted,
            'available' => $ingredient->getAvailableStockBase(),
        ]);

        return $totalCommitted;
    }

    /**
     * Obtiene un resumen de disponibilidad para un ingrediente.
     */
    public function getAvailabilitySummary(RawIngredient $ingredient): array
    {
        return [
            'ingredient_id' => $ingredient->id,
            'sku' => $ingredient->sku,
            'physical_stock' => (float) $ingredient->current_stock_base,
            'committed_stock' => (float) $ingredient->committed_stock_base,
            'pending_incoming' => (float) $ingredient->pending_incoming_base,
            'available_stock' => $ingredient->getAvailableStockBase(),
            'minimum_stock' => (float) $ingredient->minimum_stock_base,
            'reorder_level' => (float) ($ingredient->reorder_level_base ?? $ingredient->minimum_stock_base),
            'is_below_minimum' => $ingredient->isBelowMinimum(),
            'needs_reorder' => $ingredient->needsReorder(),
            'base_unit' => $ingredient->base_unit->value,
        ];
    }

    /**
     * Obtiene todos los ingredientes con stock crítico para una sucursal.
     */
    public function getCriticalStockAlerts(int $companyId, int $branchId): array
    {
        $ingredients = RawIngredient::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->get();

        $alerts = [];
        foreach ($ingredients as $ingredient) {
            if ($ingredient->needsReorder()) {
                $alerts[] = $this->getAvailabilitySummary($ingredient);
            }
        }

        return $alerts;
    }
}
