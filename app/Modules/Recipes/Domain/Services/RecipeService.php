<?php

namespace Modules\Recipes\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Recipes\Domain\Entities\ProductRecipe;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\Entities\RecipeItem;
use Modules\Recipes\Domain\Exceptions\InsufficientIngredientStockException;

/**
 * Servicio de dominio para gestión de Fichas Técnicas (BOM).
 */
class RecipeService
{
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
}
