<?php

namespace Modules\Catalog\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItem extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'product_id',
        'base_price',
        'discount_amount',
        'is_active',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * El producto tipo combo asociado.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Componentes del combo (productos incluidos).
     */
    public function components(): HasMany
    {
        return $this->hasMany(MenuItemProduct::class, 'menu_item_id');
    }

    /**
     * Reglas de sustitución del combo.
     */
    public function replacementRules(): HasMany
    {
        return $this->hasMany(MenuItemReplacementRule::class, 'menu_item_id');
    }

    /**
     * Scope: solo combos activos.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Calcula la sumatoria de productos del combo.
     */
    public function productsSum(): float
    {
        return $this->components->sum(function ($component) {
            return (float) $component->product->base_price * $component->quantity;
        });
    }

    /**
     * Calcula el precio final del combo.
     */
    public function finalPrice(): float
    {
        return (float) $this->base_price;
    }

    /**
     * Verifica si un producto puede ser sustituido en este combo.
     */
    public function canSubstitute(int $originalProductId): bool
    {
        $component = $this->components()
            ->where('product_id', $originalProductId)
            ->first();

        return $component && $component->is_substitutable;
    }
}
