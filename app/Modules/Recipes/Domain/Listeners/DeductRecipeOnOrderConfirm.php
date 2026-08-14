<?php

namespace Modules\Recipes\Domain\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Recipes\Domain\Entities\ProductRecipe;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\Exceptions\InsufficientIngredientStockException;
use Modules\Recipes\Domain\Services\RecipeService;

/**
 * Listener que descuenta automáticamente los insumos de las recetas
 * cuando un pedido es confirmado.
 * 
 * Según Anexo Técnico Recetas v2.0 - Sección 5 (Algoritmo de Deducción)
 */
class DeductRecipeOnOrderConfirm
{
    public function __construct(
        private RecipeService $recipeService
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        $order = $event->order;
        
        Log::info('DeductRecipeOnOrderConfirm: Listener ejecutado', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'branch_id' => $order->branch_id,
            'items_count' => $order->items->count(),
        ]);
        
        try {
            $this->recipeService->deductInventoryForOrder($order);
            
            Log::info('RecipeInventoryDeducted: Insumos descontados exitosamente', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
        } catch (InsufficientIngredientStockException $e) {
            Log::warning('InsufficientIngredientStock: ' . $e->getMessage(), [
                'order_id' => $order->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al descontar insumos de receta', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
