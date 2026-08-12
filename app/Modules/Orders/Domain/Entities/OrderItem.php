<?php

namespace Modules\Orders\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Domain\Entities\MenuItem;
use Modules\Companies\Domain\Entities\Company;

class OrderItem extends Model
{
    use HasFactory;
    use HasUuid;
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'order_id',
        'menu_item_id',
        'name_snapshot',
        'unit_price_snapshot',
        'quantity',
        'notes',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_snapshot' => 'decimal:2',
            'quantity' => 'integer',
            'subtotal' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (OrderItem $item) {
            $item->subtotal = $item->unit_price_snapshot * $item->quantity;
        });
    }

    // ============================================
    // Relaciones
    // ============================================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class);
    }
}
