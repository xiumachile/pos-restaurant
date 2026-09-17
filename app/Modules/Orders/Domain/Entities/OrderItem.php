<?php

namespace Modules\Orders\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use App\Shared\Domain\Traits\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Domain\Entities\MenuItem;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Companies\Domain\Entities\Company;

class OrderItem extends Model
{
    use HasFactory;
    use HasUuid;
    use Syncable;
    use BelongsToTenant;

    protected $fillable = [
        'product_id',
        'company_id',
        'order_id',
        'menu_item_id',
        'name_snapshot',
        'unit_price_snapshot',
        'quantity',
        'notes',
        'subtotal',
        'tax_amount',
        'tax_rate_snapshot',
        'tax_name_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_snapshot' => 'integer',
            'product_id' => 'integer',
            'quantity' => 'integer',
            'subtotal' => 'integer',
            'tax_amount' => 'integer',
            'tax_rate_snapshot' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (OrderItem $item) {
            // ADR-011: unit_price_snapshot es BRUTO (IVA incluido)
            $item->subtotal = $item->unit_price_snapshot * $item->quantity;
            
            // ADR-011: NO calcular tax_amount por item
            // El tax se calcula a nivel de Order usando modelo bruto:
            // net_amount = gross / 1.19, tax_amount = gross - net_amount
            // 
            // Esto es porque los precios del catálogo son BRUTOS (IVA incluido)
            // y el cálculo de tax debe hacerse sobre el total del order,
            // no item por item (evita errores de redondeo).
            
            // Mantener tax_amount = 0 para compatibilidad con schema
            if ($item->tax_amount === null) {
                $item->tax_amount = 0;
            }
            
            // Mantener snapshots para auditoría
            if ($item->tax_rate_snapshot === null) {
                $item->tax_rate_snapshot = 19.00; // IVA Chile
                $item->tax_name_snapshot = 'IVA 19%';
            }
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

}