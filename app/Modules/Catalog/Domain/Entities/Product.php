<?php

namespace Modules\Catalog\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasTranslations;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
