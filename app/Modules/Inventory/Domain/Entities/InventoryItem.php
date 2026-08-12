<?php

namespace Modules\Inventory\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasTranslations;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Inventory\Domain\ValueObjects\StockStatus;

class InventoryItem extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use HasTranslations;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'sku',
        'name_translations',
        'unit',
        'cost_price',
        'min_stock',
        'is_active',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'cost_price' => 'decimal:2',
        'min_stock' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected array $translatableFields = ['name_translations'];

    /**
     * Stocks por sucursal.
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    /**
     * Movimientos de stock.
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Obtiene el stock de una sucursal específica.
     */
    public function stockForBranch(int $branchId): float
    {
        return (float) ($this->stocks()
            ->where('branch_id', $branchId)
            ->value('quantity') ?? 0);
    }

    /**
     * Obtiene el estado del stock para una sucursal.
     */
    public function stockStatusForBranch(int $branchId): StockStatus
    {
        $quantity = $this->stockForBranch($branchId);
        return StockStatus::fromQuantity($quantity, (float) $this->min_stock);
    }

    /**
     * Scope: solo items activos.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
