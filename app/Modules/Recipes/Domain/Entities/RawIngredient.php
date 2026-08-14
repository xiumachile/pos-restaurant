<?php

namespace Modules\Recipes\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasTranslations;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Recipes\Domain\ValueObjects\BaseUnit;
use Modules\Recipes\Domain\ValueObjects\DimensionType;

/**
 * Materia prima / Insumo base.
 * Stock almacenado SIEMPRE en unidad base SI (g/ml/un).
 */
class RawIngredient extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'raw_ingredients';

    protected $fillable = [
        'company_id',
        'branch_id',
        'sku',
        'name_translations',
        'dimension_type',
        'base_unit',
        'current_stock_base',
        'minimum_stock_base',
        'cost_per_base_unit',
        'is_active',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'dimension_type' => DimensionType::class,
        'base_unit' => BaseUnit::class,
        'current_stock_base' => 'decimal:4',
        'minimum_stock_base' => 'decimal:4',
        'cost_per_base_unit' => 'decimal:6',
        'is_active' => 'boolean',
    ];

    protected array $translatableFields = ['name_translations'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(RawIngredientPurchase::class);
    }

    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    /**
     * Registra una compra y recalcula el costo promedio ponderado.
     */
    public function registerPurchase(
        string $purchaseUnitName,
        float $purchaseQuantity,
        float $conversionFactorToBase,
        float $totalPurchaseCost,
        int $userId
    ): RawIngredientPurchase {
        // Calcular cantidad total en unidad base
        $totalBaseQuantity = round($purchaseQuantity * $conversionFactorToBase, 4);

        // Calcular nuevo costo por unidad base
        $costPerBaseUnit = round($totalPurchaseCost / $totalBaseQuantity, 6);

        // Calcular nuevo costo promedio ponderado
        $currentStock = (float) $this->current_stock_base;
        $currentCost = (float) $this->cost_per_base_unit;

        $totalCurrentValue = ($currentStock * $currentCost) + $totalPurchaseCost;
        $totalNewStock = $currentStock + $totalBaseQuantity;

        $newCostPerBaseUnit = $totalNewStock > 0
            ? round($totalCurrentValue / $totalNewStock, 6)
            : $costPerBaseUnit;

        // Crear registro de compra
        $purchase = RawIngredientPurchase::create([
            'raw_ingredient_id' => $this->id,
            'user_id' => $userId,
            'purchase_unit_name' => $purchaseUnitName,
            'purchase_quantity' => $purchaseQuantity,
            'conversion_factor_to_base' => $conversionFactorToBase,
            'total_base_quantity_added' => $totalBaseQuantity,
            'total_purchase_cost' => $totalPurchaseCost,
            'calculated_cost_per_base_unit' => $costPerBaseUnit,
        ]);

        // Actualizar stock y costo promedio
        $this->current_stock_base = $totalNewStock;
        $this->cost_per_base_unit = $newCostPerBaseUnit;
        $this->save();

        return $purchase;
    }

    /**
     * Descuenta stock en unidad base SI.
     * Usado por RecipeService al confirmar pedido.
     */
    public function deductStock(float $quantityBase): void
    {
        $branch = $this->branch;
        $allowNegative = $branch?->allow_negative_stock ?? false;

        if (!$allowNegative && (float) $this->current_stock_base < $quantityBase) {
            throw new \Modules\Recipes\Domain\Exceptions\InsufficientIngredientStockException(
                $this, $quantityBase, (float) $this->current_stock_base
            );
        }

        $this->decrement('current_stock_base', $quantityBase);
    }

    /**
     * Costo total del stock actual.
     */
    public function totalStockValue(): float
    {
        return round((float) $this->current_stock_base * (float) $this->cost_per_base_unit, 2);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
