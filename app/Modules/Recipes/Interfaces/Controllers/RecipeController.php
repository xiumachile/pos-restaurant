<?php

namespace Modules\Recipes\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Recipes\Domain\Entities\ProductRecipe;
use Modules\Recipes\Domain\Services\RecipeService;
use Modules\Recipes\Interfaces\Requests\CreateRecipeRequest;
use Modules\Recipes\Interfaces\Requests\UpdateRecipeRequest;
use Modules\Recipes\Interfaces\Resources\ProductRecipeResource;

class RecipeController extends Controller
{
    public function __construct(
        private RecipeService $recipeService
    ) {}

    /**
     * POST /api/v1/recipes
     * Crea una ficha técnica para un producto.
     */
    public function store(CreateRecipeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Buscar producto evitando conflicto con BelongsToTenant del módulo Catalog
        $product = Product::withoutGlobalScopes()
            ->where('uuid', $validated['product_uuid'])
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        // Verificar que el producto no tenga receta ya
        $existingRecipe = ProductRecipe::where('product_id', $product->id)
            ->where('company_id', $user->company_id)
            ->first();

        if ($existingRecipe) {
            return response()->json([
                'error' => 'recipe_exists',
                'message' => 'El producto ya tiene una ficha técnica. Use PUT para actualizarla.',
            ], 422);
        }

        $recipe = $this->recipeService->createRecipe(
            product: $product,
            ingredients: $validated['ingredients'],
            companyId: $user->company_id,
            description: $validated['description'] ?? null,
            yieldServings: (int) ($validated['yield_servings'] ?? 1)
        );

        // Cargar relaciones necesarias para el recurso
        $recipe->load(['items.ingredient']);
        $recipe->setRelation('product', $product); // Establecer product manualmente

        return ProductRecipeResource::make($recipe)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/recipes/product/{uuid}
     * Obtiene la receta de un producto.
     */
    public function showByProduct(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        // Buscar producto evitando conflicto con BelongsToTenant del módulo Catalog
        $product = Product::withoutGlobalScopes()
            ->where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $recipe = ProductRecipe::where('product_id', $product->id)
            ->where('company_id', $user->company_id)
            ->with(['items.ingredient'])
            ->first();

        if (!$recipe) {
            return response()->json([
                'error' => 'recipe_not_found',
                'message' => 'El producto no tiene ficha técnica.',
            ], 404);
        }

        // Establecer product manualmente para evitar problemas de scope
        $recipe->setRelation('product', $product);

        return ProductRecipeResource::make($recipe)->response();
    }

    /**
     * PUT /api/v1/recipes/{uuid}
     * Actualiza una receta existente.
     */
    public function update(UpdateRecipeRequest $request, string $uuid): JsonResponse
    {
        $validated = $request->validated();

        $recipe = ProductRecipe::where('uuid', $uuid)
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();

        $recipe = $this->recipeService->updateRecipe(
            recipe: $recipe,
            ingredients: $validated['ingredients']
        );

        // Actualizar campos opcionales
        if (isset($validated['description'])) {
            $recipe->description = $validated['description'];
        }
        if (isset($validated['yield_servings'])) {
            $recipe->yield_servings = (int) $validated['yield_servings'];
        }
        $recipe->save();

        // Cargar relaciones necesarias para el recurso
        $recipe->load(['items.ingredient']);
        
        // Cargar product manualmente
        $product = \Modules\Catalog\Domain\Entities\Product::find($recipe->product_id);
        $recipe->setRelation('product', $product);

        return ProductRecipeResource::make($recipe)->response();
    }

    /**
     * DELETE /api/v1/recipes/{uuid}
     * Elimina una receta (soft delete).
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $recipe = ProductRecipe::where('uuid', $uuid)
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();

        $recipe->delete();

        return response()->json(['message' => 'Receta eliminada correctamente.'], 200);
    }

    /**
     * GET /api/v1/recipes/food-cost
     * Reporte de Food Cost por producto.
     */
    public function foodCostReport(Request $request): JsonResponse
    {
        $user = $request->user();

        $report = $this->recipeService->calculateFoodCostReport($user->company_id);

        return response()->json(['data' => $report]);
    }
}
