<?php

namespace Modules\Recipes\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\Services\IngredientService;
use Modules\Recipes\Interfaces\Requests\CreateIngredientRequest;
use Modules\Recipes\Interfaces\Requests\RegisterPurchaseRequest;
use Modules\Recipes\Interfaces\Resources\RawIngredientResource;

class IngredientController extends Controller
{
    public function __construct(
        private IngredientService $ingredientService
    ) {}

    /**
     * POST /api/v1/recipes/ingredients
     * Crea un insumo base.
     */
    public function store(CreateIngredientRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $ingredient = $this->ingredientService->createIngredient(
            companyId: $user->company_id,
            branchId: $user->branch_id,
            sku: $validated['sku'],
            nameTranslations: $validated['name_translations'],
            dimensionType: $validated['dimension_type'],
            baseUnit: $validated['base_unit'],
            minimumStockBase: (float) ($validated['minimum_stock_base'] ?? 0),
            initialCostPerBaseUnit: (float) ($validated['initial_cost_per_base_unit'] ?? 0)
        );

        return RawIngredientResource::make($ingredient)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/recipes/ingredients
     * Lista insumos con opción de filtrar por stock bajo.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $lowStockOnly = $request->query('low_stock', false);

        if ($lowStockOnly) {
            $ingredients = $this->ingredientService->getLowStockIngredients(
                companyId: $user->company_id,
                branchId: $user->branch_id
            );
            return response()->json(['data' => $ingredients]);
        }

        $ingredients = RawIngredient::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->orderBy('sku')
            ->get();

        return RawIngredientResource::collection($ingredients)->response();
    }

    /**
     * GET /api/v1/recipes/ingredients/{uuid}
     * Obtiene un insumo específico.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $ingredient = RawIngredient::where('uuid', $uuid)->firstOrFail();

        return RawIngredientResource::make($ingredient)->response();
    }

    /**
     * POST /api/v1/recipes/ingredients/{uuid}/purchase
     * Registra una compra de insumo.
     */
    public function purchase(RegisterPurchaseRequest $request, string $uuid): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $ingredient = RawIngredient::where('uuid', $uuid)->firstOrFail();

        $purchase = $this->ingredientService->registerPurchase(
            ingredient: $ingredient,
            purchaseUnitName: $validated['purchase_unit_name'],
            purchaseQuantity: (float) $validated['purchase_quantity'],
            totalPurchaseCost: (float) $validated['total_purchase_cost'],
            userId: $user->id,
            conversionFactor: isset($validated['conversion_factor_to_base'])
                ? (float) $validated['conversion_factor_to_base']
                : null
        );

        return response()->json([
            'message' => 'Compra registrada exitosamente.',
            'data' => [
                'purchase_uuid' => $purchase->uuid,
                'ingredient_uuid' => $ingredient->uuid,
                'total_base_quantity_added' => (float) $purchase->total_base_quantity_added,
                'new_stock_base' => (float) $ingredient->fresh()->current_stock_base,
                'new_cost_per_base_unit' => (float) $ingredient->fresh()->cost_per_base_unit,
            ],
        ], 201);
    }

    /**
     * GET /api/v1/recipes/ingredients/{uuid}/purchases
     * Obtiene historial de compras de un insumo.
     */
    public function purchases(Request $request, string $uuid): JsonResponse
    {
        $ingredient = RawIngredient::where('uuid', $uuid)->firstOrFail();

        $history = $this->ingredientService->getPurchaseHistory(
            ingredientId: $ingredient->id,
            limit: (int) $request->query('limit', 20)
        );

        return response()->json(['data' => $history]);
    }
}
