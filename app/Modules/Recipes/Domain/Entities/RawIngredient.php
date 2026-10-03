<?php

namespace Modules\Recipes\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasTranslations;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Recipes\Domain\Entities\RawIngredientMovement;
use Modules\Recipes\Domain\ValueObjects\MovementType;
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
        int $userId,
        ?string $documentType = null,
        ?string $documentNumber = null,
        ?string $supplierName = null,
        ?string $supplierRut = null
    ): RawIngredientPurchase {
        return DB::transaction(function () use (
            $purchaseUnitName,
            $purchaseQuantity,
            $conversionFactorToBase,
            $totalPurchaseCost,
            $userId,
            $documentType,
            $documentNumber,
            $supplierName,
            $supplierRut
        ) {
            // Calcular cantidad total en unidad base
            $totalBaseQuantity = round($purchaseQuantity * $conversionFactorToBase, 4);

            // Calcular costo por unidad base de esta compra específica
            $costPerBaseUnit = $totalBaseQuantity > 0
                ? round($totalPurchaseCost / $totalBaseQuantity, 6)
                : 0;

            // Calcular costo promedio ponderado ANTES del movimiento
            $currentStock = (float) $this->current_stock_base;
            $currentCost = (float) $this->cost_per_base_unit;
            $totalCurrentValue = ($currentStock * $currentCost) + $totalPurchaseCost;
            $totalNewStock = $currentStock + $totalBaseQuantity;

            $newCostPerBaseUnit = $totalNewStock > 0
                ? round($totalCurrentValue / $totalNewStock, 6)
                : $costPerBaseUnit;

            // 1. Crear registro de compra con documento contable
            $purchase = RawIngredientPurchase::create([
                'raw_ingredient_id' => $this->id,
                'user_id' => $userId,
                'purchase_unit_name' => $purchaseUnitName,
                'purchase_quantity' => $purchaseQuantity,
                'conversion_factor_to_base' => $conversionFactorToBase,
                'total_base_quantity_added' => $totalBaseQuantity,
                'total_purchase_cost' => $totalPurchaseCost,
                'calculated_cost_per_base_unit' => $costPerBaseUnit,
                'purchase_date' => now(),
                'document_type' => $documentType,
                'document_number' => $documentNumber,
                'supplier_name' => $supplierName,
                'supplier_rut' => $supplierRut,
            ]);

            // 2. Crear movimiento in_purchase (esto actualiza el stock automáticamente)
            $reason = "Compra de {$purchaseQuantity} {$purchaseUnitName}";
            if ($documentType && $documentNumber) {
                $reason .= " ({$documentType} #{$documentNumber})";
            }

            RawIngredientMovement::record(
                companyId: $this->company_id,
                branchId: $this->branch_id,
                rawIngredientId: $this->id,
                type: MovementType::InPurchase,
                quantityBase: (float) $totalBaseQuantity,
                referenceType: 'purchase',
                referenceId: $purchase->id,
                reason: $reason
            );

            // 3. Actualizar solo el costo promedio ponderado (el stock ya lo actualizó el movimiento)
            $this->cost_per_base_unit = $newCostPerBaseUnit;
            $this->save();

            return $purchase;
        });
    }

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
