<?php

namespace Modules\Orders\Domain\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Identity\Domain\Entities\User;

class OrderItemModifier extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_item_id',
        'original_product_id',
        'substitute_product_id',
        'added_product_id',
        'price_adjustment',
        'reason',
        'requires_authorization',
        'authorized_by',
    ];

    protected function casts(): array
    {
        return [
            'price_adjustment' => 'decimal:2',
            'requires_authorization' => 'boolean',
        ];
    }

    // ============================================
    // Relaciones
    // ============================================

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function originalProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'original_product_id');
    }

    public function substituteProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'substitute_product_id');
    }

    public function addedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'added_product_id');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }
}
