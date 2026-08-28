<?php

namespace Modules\Recipes\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Recipes\Domain\Entities\ProductRecipe;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\Entities\RecipeItem;
use Modules\Recipes\Domain\Exceptions\InsufficientIngredientStockException;
use App\Shared\Application\TenantContext;

/**
 * Servicio de dominio para gestión de Fichas Técnicas (BOM).
 */
class RecipeService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Crea una ficha técnica para un producto con sus ingredientes.
     *
     * @param Product $product
     * @param array $ingredients Array de ['raw_ingredient_id', 'quantity_base_unit', 'waste_percentage']
     * @param int $companyId
     * @param string|null $description
     * @param int $yieldServings
     * @return ProductRecipe
     */
    public function createRecipe(
        Product $product,
        array $ingredients,
        int $companyId,
        ?string $description = null,
        int $yieldServings = 1
    ): ProductRecipe {
        return DB::transaction(function () use ($product, $ingredients, $companyId, $description, $yieldServings) {
            $recipe = ProductRecipe::create([
                'company_id' => $companyId,
                'product_id' => $product->id,
                'description' => $description,
                'yield_servings' => $yieldServings,
                'total_recipe_cost' => 0,
            ]);

            foreach ($ingredients as $ingredientData) {
                $ingredient = RawIngredient::findOrFail($ingredientData['raw_ingredient_id']);
                
                // S1.3: Validar ownership del ingrediente
                $this->validateIngredientOwnership($ingredient);

                RecipeItem::createWithCalculation(
                    recipeId: $recipe->id,
                    ingredient: $ingredient,
                    quantityBase: (float) $ingredientData['quantity_base_unit'],
                    wastePercentage: (float) ($ingredientData['waste_percentage'] ?? 0)
                );
            }

            // Recalcular costo total
            $recipe->load('items');
            $recipe->recalculateTotalCost();

            return $recipe;
        });
    }

    /**
     * Actualiza una receta existente (reemplaza todos los ingredientes).
     */
    public function updateRecipe(
        ProductRecipe $recipe,
        array $ingredients
    ): ProductRecipe {
        return DB::transaction(function () use ($recipe, $ingredients) {
            // Eliminar ingredientes existentes
            $recipe->items()->delete();

            // Agregar nuevos ingredientes
            foreach ($ingredients as $ingredientData) {
                $ingredient = RawIngredient::findOrFail($ingredientData['raw_ingredient_id']);
                
                // S1.3: Validar ownership del ingrediente
                $this->validateIngredientOwnership($ingredient);

                RecipeItem::createWithCalculation(
                    recipeId: $recipe->id,
                    ingredient: $ingredient,
                    quantityBase: (float) $ingredientData['quantity_base_unit'],
                    wastePercentage: (float) ($ingredientData['waste_percentage'] ?? 0)
                );
            }

            // Recalcular costo
            $recipe->load('items');
            $recipe->recalculateTotalCost();

            return $recipe;
        });
    }

    /**
     * Calcula el Food Cost de todas las recetas de una empresa.
     * Retorna array con product_id, food_cost_percentage, gross_margin.
     */
    public function calculateFoodCostReport(int $companyId): array
    {
        $recipes = ProductRecipe::where('company_id', $companyId)
            ->with(['items.ingredient'])
            ->get();

        return $recipes->map(function (ProductRecipe $recipe) use ($companyId) {
            // Cargar producto evitando scope BelongsToTenant del módulo Catalog
            $product = \Modules\Catalog\Domain\Entities\Product::withoutGlobalScopes()
                ->where('id', $recipe->product_id)
                ->where('company_id', $companyId)
                ->first();

            $recipe->load('items');
            $recipe->recalculateTotalCost();

            return [
                'product_id' => $recipe->product_id,
                'product_name' => $product?->name_translations['es'] ?? 'N/A',
                'product_base_price' => (float) ($product?->base_price ?? 0),
                'recipe_cost' => (float) $recipe->total_recipe_cost,
                'food_cost_percentage' => $product ? round(((float) $recipe->total_recipe_cost / (float) $product->base_price) * 100, 2) : 0,
                'gross_margin' => $product ? max(0, (float) $product->base_price - (float) $recipe->total_recipe_cost) : 0,
                'recipe_cost' => (float) $recipe->total_recipe_cost,
            ];
        })->toArray();
    }

    /**
     * Descuenta automáticamente los insumos de las recetas de todos los productos del pedido.
     * Se ejecuta cuando un pedido es confirmado (OrderConfirmed event).
     * 
     * Según Anexo Técnico Recetas v2.0 - Sección 5
     */
    public function deductInventoryForOrder(\Modules\Orders\Domain\Entities\Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items as $orderItem) {
                $this->deductRecipeInventoryForOrderItem($orderItem, $order->branch_id);
            }
        });
    }

    /**
     * Descuenta los insumos de la receta de un ítem específico del pedido.
     */
    private function deductRecipeInventoryForOrderItem($orderItem, int $branchId): void
    {
        Log::info('Buscando receta para producto', [
            'product_id' => $orderItem->product_id,
            'company_id' => $orderItem->company_id,
            'branch_id' => $branchId,
        ]);

        // Buscar la receta del producto (sin Global Scopes de Catalog)
        $recipe = ProductRecipe::withoutGlobalScopes()
            ->where('product_id', $orderItem->product_id)
            ->where('company_id', $orderItem->company_id)
            ->with(['items.ingredient' => function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            }])
            ->first();

        if (!$recipe) {
            Log::info('Producto sin receta, saltando', [
                'product_id' => $orderItem->product_id,
            ]);
            return;
        }

        Log::info('Receta encontrada', [
            'recipe_id' => $recipe->id,
            'items_count' => $recipe->items->count(),
        ]);

        foreach ($recipe->items as $recipeItem) {
            $ingredient = $recipeItem->ingredient;
            
            if (!$ingredient) {
                Log::warning('Ingredient not found in branch', [
                    'recipe_item_id' => $recipeItem->id,
                    'branch_id' => $branchId,
                ]);
                continue;
            }

            // Cantidad a descontar = cantidad efectiva * cantidad de platos pedidos
            $quantityToDeduct = (float) $recipeItem->effective_discount_base_quantity * (int) $orderItem->quantity;

            Log::info('Descontando stock de ingrediente', [
                'ingredient_id' => $ingredient->id,
                'ingredient_sku' => $ingredient->sku,
                'quantity_to_deduct' => $quantityToDeduct,
                'current_stock' => (float) $ingredient->current_stock_base,
            ]);

            // Descontar stock (lanza InsufficientIngredientStockException si no hay suficiente)
            $ingredient->deductStock($quantityToDeduct);
            
            Log::info('Stock descontado exitosamente', [
                'ingredient_id' => $ingredient->id,
                'new_stock' => (float) $ingredient->fresh()->current_stock_base,
            ]);
        }
    }

    /**
     * S1.3: Valida que el ingrediente pertenezca al tenant del usuario.
     */
    private function validateIngredientOwnership(RawIngredient $ingredient): void
    {
        if (!$this->tenantContext->hasCompany()) {
            throw new \RuntimeException('TenantContext no establecido');
        }

        if ($ingredient->company_id !== $this->tenantContext->companyId()) {
            abort(403, 'No autorizado para acceder a este ingrediente');
        }
    }
}
