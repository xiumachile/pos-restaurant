<?php

namespace Modules\Catalog\Domain\Entities;

use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Producto asignado a una carta, con posición y disponibilidad.
 * Se accede siempre a través del menú (que tiene tenant scope).
 */
class MenuProduct extends Model
{
    use HasUuid;

    protected $fillable = [
        'menu_id',
        'product_id',
        'position',
        'is_available',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_available' => 'boolean',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
