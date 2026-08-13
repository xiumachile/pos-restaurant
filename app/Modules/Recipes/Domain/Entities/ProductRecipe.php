<?php

namespace Modules\Recipes\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Companies\Domain\Entities\Company;

/**
 * Ficha técnica (Bill of Materials) de un producto vendido.
 * Define los ingredientes necesarios para preparar el producto.
 */
class ProductRecipe extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'product_recipes';

    protected $fillable = [
        'company_id',
        'product_id',
        'description',
        'yield_servings',
        'total_recipe_cost',
    ];

    protected $casts = [
        'yield_servings' => 'integer',
        'total_recipe_cost' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecipeItem::class, 'recipe_id');
    }

    /**
     * Recalcula el costo total de la receta sumando los costos de los ingredientes.
     */
    public function recalculateTotalCost(): void
    {
        $total = $this->items->sum(function (RecipeItem $item) {
            return (float) $item->calculated_item_cost;
        });

        $this->total_recipe_cost = round($total, 2);
        $this->save();
    }

    /**
     * Calcula el Food Cost % de la receta respecto al precio de venta del producto.
     * Food Cost % = (Costo Receta / Precio Venta) * 100
     */
    public function calculateFoodCostPercentage(): float
    {
        $productPrice = (float) ($this->product?->base_price ?? 0);
        if ($productPrice <= 0) {
            return 0.0;
        }
        return round(((float) $this->total_recipe_cost / $productPrice) * 100, 2);
    }

    /**
     * Calcula el margen bruto en moneda.
     */
    public function calculateGrossMargin(): float
    {
        $productPrice = (float) ($this->product?->base_price ?? 0);
        return max(0, $productPrice - (float) $this->total_recipe_cost);
    }
}
