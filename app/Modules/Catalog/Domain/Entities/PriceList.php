<?php

namespace Modules\Catalog\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lista de precios configurable (precio_comedor, precio_delivery, etc.).
 * El cliente puede crear N listas según su necesidad.
 * Las cartas (menús) seleccionan una lista mediante price_list_id.
 */
class PriceList extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'display_name',
        'channel_type',
        'currency',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Precios de productos asociados a esta lista.
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }
}
