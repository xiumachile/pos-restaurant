<?php

namespace Modules\Catalog\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasTranslations;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tax\Domain\Entities\Tax;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use HasTranslations;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'category_id',
        'tax_id',
        'sku',
        'name_translations',
        'description_translations',
        'base_price',
        'tax_rate',
        'is_combo',
        'kitchen_zone_id',
        'is_active',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'description_translations' => 'array',
        'base_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'is_combo' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected array $translatableFields = ['name_translations', 'description_translations'];

    /**
     * Un producto pertenece a una categoría.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Impuesto asociado al producto.
     */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    /**
     * Obtiene el impuesto efectivo del producto.
     * Herencia: Product.tax -> Category.tax -> Tax default de la empresa
     * 
     * @return Tax|null Impuesto efectivo o null si no hay ninguno
     */
    public function getEffectiveTax(): ?Tax
    {
        // 1. Si el producto tiene tax_id, usarlo
        if ($this->tax_id && $this->tax) {
            return $this->tax;
        }

        // 2. Si la categoría tiene tax_id, usarlo
        if ($this->category && $this->category->tax_id && $this->category->tax) {
            return $this->category->tax;
        }

        // 3. Usar el impuesto por defecto de la empresa
        return Tax::where('company_id', $this->company_id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Calcula el impuesto para este producto.
     * 
     * @param float $quantity Cantidad de unidades
     * @return float Monto del impuesto
     */
    public function calculateTax(float $quantity = 1): float
    {
        $tax = $this->getEffectiveTax();
        
        if (!$tax) {
            return 0.0;
        }

        $baseAmount = (float) $this->base_price * $quantity;
        return $tax->calculate($baseAmount, $quantity);
    }


    /**
     * Si es combo, tiene un MenuItem asociado.
     */
    public function menuItem(): HasOne
    {
        return $this->hasOne(MenuItem::class);
    }

    /**
     * Productos que lo incluyen como componente de combo.
     */
    public function menuItemProducts(): HasMany
    {
        return $this->hasMany(MenuItemProduct::class);
    }

    /**
     * Precios del producto por lista de precios.
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * Resuelve el precio efectivo del producto para una lista de precios.
     * Jerarquía: lista indicada → lista default de la empresa → base_price.
     */
    public function resolvePrice(?PriceList $priceList = null): float
    {
        if ($priceList) {
            $price = $this->prices()->where('price_list_id', $priceList->id)->first();
            if ($price) {
                return (float) $price->price;
            }
        }

        $defaultList = PriceList::where('company_id', $this->company_id)
            ->where('is_default', true)
            ->first();

        if ($defaultList) {
            $price = $this->prices()->where('price_list_id', $defaultList->id)->first();
            if ($price) {
                return (float) $price->price;
            }
        }

        return (float) $this->base_price;
    }

    /**
     * Scope: solo productos activos.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: solo productos simples (no combos).
     */
    public function scopeSimple($query)
    {
        return $query->where('is_combo', false);
    }

    /**
     * Scope: solo combos.
     */
    public function scopeCombos($query)
    {
        return $query->where('is_combo', true);
    }

    /**
     * Scope: filtrar por categoría.
     */
    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Obtiene el nombre traducido.
     */
    public function getNameAttribute(): string
    {
        return $this->translate('name_translations', null, 'Sin nombre');
    }

    /**
     * Obtiene la descripción traducida.
     */
    public function getDescriptionAttribute(): string
    {
        return $this->translate('description_translations', null, '');
    }

    /**
     * Calcula el precio con impuesto incluido.
     */
    public function priceWithTax(): float
    {
        return (float) $this->base_price * (1 + (float) $this->tax_rate / 100);
    }
}
