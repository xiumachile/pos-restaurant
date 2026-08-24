<?php

namespace Modules\Catalog\Domain\Entities;

use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Precio de un producto en una lista de precios específica.
 * Se consulta siempre a través del producto (que sí tiene tenant scope).
 */
class ProductPrice extends Model
{
    use HasUuid;

    protected $fillable = [
        'product_id',
        'price_list_id',
        'price',
        'currency',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }
}
