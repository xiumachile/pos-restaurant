<?php

namespace Modules\Recipes\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\Entities\RawIngredientPurchase;
use Modules\Recipes\Domain\ValueObjects\BaseUnit;
use Modules\Recipes\Domain\ValueObjects\DimensionType;

/**
 * Servicio de dominio para gestión de Insumos (Materia Prima).
 */
class IngredientService
{
    /**
     * Crea un insumo base con unidad SI.
     */
    public function createIngredient(
        int $companyId,
        int $branchId,
        string $sku,
        array $nameTranslations,
        string $dimensionType,
        string $baseUnit,
        float $minimumStockBase = 0,
        float $initialCostPerBaseUnit = 0
    ): RawIngredient {
        return RawIngredient::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'sku' => $sku,
            'name_translations' => $nameTranslations,
            'dimension_type' => $dimensionType,
            'base_unit' => $baseUnit,
            'current_stock_base' => 0,
            'minimum_stock_base' => $minimumStockBase,
            'cost_per_base_unit' => $initialCostPerBaseUnit,
            'is_active' => true,
        ]);
    }

    /**
     * Registra una compra de insumo con conversión automática.
     *
     * @param RawIngredient $ingredient
     * @param string $purchaseUnitName Ej: "Saco 25Kg", "Bidón 10L"
     * @param float $purchaseQuantity Ej: 2 (sacos)
     * @param float $totalPurchaseCost Ej: 150000 (CLP)
     * @param int $userId
     * @param float|null $conversionFactor Si es null, se calcula automáticamente
     * @return RawIngredientPurchase
     */
    public function registerPurchase(
        RawIngredient $ingredient,
        string $purchaseUnitName,
        float $purchaseQuantity,
        float $totalPurchaseCost,
        int $userId,
        ?float $conversionFactor = null
    ): RawIngredientPurchase {
        // Si no se proporciona factor, usar el de la unidad base del insumo
        if ($conversionFactor === null) {
            $conversionFactor = $ingredient->base_unit->conversionFactorToBase();
        }

        return $ingredient->registerPurchase(
            purchaseUnitName: $purchaseUnitName,
            purchaseQuantity: $purchaseQuantity,
            conversionFactorToBase: $conversionFactor,
            totalPurchaseCost: $totalPurchaseCost,
            userId: $userId
        );
    }

    /**
     * Obtiene insumos con stock bajo (current_stock < minimum_stock).
     */
    public function getLowStockIngredients(int $companyId, ?int $branchId = null): array
    {
        $query = RawIngredient::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereColumn('current_stock_base', '<', 'minimum_stock_base');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('sku')->get()->toArray();
    }

    /**
     * Obtiene el historial de compras de un insumo.
     */
    public function getPurchaseHistory(int $ingredientId, int $limit = 20): array
    {
        return RawIngredientPurchase::where('raw_ingredient_id', $ingredientId)
            ->with('user')
            ->orderByDesc('purchase_date')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
