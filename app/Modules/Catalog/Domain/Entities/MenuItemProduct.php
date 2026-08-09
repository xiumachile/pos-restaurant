<?php

namespace Modules\Catalog\Domain\Entities;

use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemProduct extends Model
{
    use HasUuid;

    protected $fillable = [
        'menu_item_id',
        'product_id',
        'quantity',
        'is_substitutable',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'is_substitutable' => 'boolean',
    ];

    /**
     * El combo al que pertenece.
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * El producto incluido en el combo.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calcula el subtotal de este componente.
     */
    public function subtotal(): float
    {
        return (float) $this->product->base_price * $this->quantity;
    }
}
