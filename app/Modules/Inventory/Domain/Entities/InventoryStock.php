<?php

namespace Modules\Inventory\Domain\Entities;

use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Inventory\Domain\ValueObjects\StockStatus;

class InventoryStock extends Model
{
    use HasUuid;

    protected $fillable = [
        'company_id',
        'branch_id',
        'inventory_item_id',
        'quantity',
        'last_movement_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'last_movement_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Estado del stock (available, low_stock, out_of_stock).
     */
    public function getStatusAttribute(): StockStatus
    {
        $minStock = (float) ($this->item?->min_stock ?? 0);
        return StockStatus::fromQuantity((float) $this->quantity, $minStock);
    }

    /**
     * Verifica si hay stock suficiente para una cantidad dada.
     */
    public function hasEnoughStock(float $quantity): bool
    {
        return (float) $this->quantity >= $quantity;
    }
}
