<?php

namespace Modules\Catalog\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Carta de venta por sucursal.
 * El sistema resuelve automáticamente qué carta usar según reglas
 * de activación (canal, horario, día). El mesero NO la selecciona.
 */
class Menu extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'description',
        'price_list_id',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(MenuActivation::class);
    }

    public function menuProducts(): HasMany
    {
        return $this->hasMany(MenuProduct::class)->orderBy('position');
    }

    /**
     * Productos de la carta con su relación al producto.
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'menu_products')
            ->withPivot(['position', 'is_available'])
            ->orderBy('menu_products.position');
    }
}
