<?php

namespace Modules\Recipes\Domain\Entities;

use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ingrediente específico dentro de una receta con cantidad, merma y costo calculado.
 * Cantidad efectiva = quantity_base_unit * (1 + waste_percentage / 100)
 */
class RecipeItem extends Model
{
    use HasUuid;

    protected $table = 'recipe_items';

    protected $fillable = [
        'recipe_id',
        'raw_ingredient_id',
        'quantity_base_unit',
        'waste_percentage',
        'effective_discount_base_quantity',
        'calculated_item_cost',
    ];

    protected $casts = [
        'quantity_base_unit' => 'decimal:4',
        'waste_percentage' => 'decimal:2',
        'effective_discount_base_quantity' => 'decimal:4',
        'calculated_item_cost' => 'decimal:2',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(ProductRecipe::class, 'recipe_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(RawIngredient::class, 'raw_ingredient_id');
    }

    /**
     * Calcula cantidad efectiva considerando merma.
     * Ejemplo: 180g + 10% merma = 198g efectivos a descontar.
     */
    public static function calculateEffectiveQuantity(float $quantityBase, float $wastePercentage): float
    {
        return round($quantityBase * (1 + ($wastePercentage / 100)), 4);
    }

    /**
     * Calcula el costo del ítem basado en cantidad efectiva * costo unitario del insumo.
     */
    public static function calculateItemCost(float $effectiveQuantity, float $costPerBaseUnit): float
    {
        return round($effectiveQuantity * $costPerBaseUnit, 2);
    }

    /**
     * Crea un recipe item calculando automáticamente cantidad efectiva y costo.
     */
    public static function createWithCalculation(
        int $recipeId,
        RawIngredient $ingredient,
        float $quantityBase,
        float $wastePercentage = 0
    ): self {
        $effective = self::calculateEffectiveQuantity($quantityBase, $wastePercentage);
        $cost = self::calculateItemCost($effective, (float) $ingredient->cost_per_base_unit);

        return self::create([
            'recipe_id' => $recipeId,
            'raw_ingredient_id' => $ingredient->id,
            'quantity_base_unit' => $quantityBase,
            'waste_percentage' => $wastePercentage,
            'effective_discount_base_quantity' => $effective,
            'calculated_item_cost' => $cost,
        ]);
    }
}
