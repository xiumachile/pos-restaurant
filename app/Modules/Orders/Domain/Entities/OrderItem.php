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
            'unit_price_snapshot' => 'decimal:2',
            'product_id' => 'integer',
            'quantity' => 'integer',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'tax_rate_snapshot' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (OrderItem $item) {
            $item->subtotal = $item->unit_price_snapshot * $item->quantity;
            
            // Si el tax_amount ya fue seteado explícitamente por el controller
            // (con su snapshot de rate), respetar ese cálculo.
            // Esto permite que OrderItemController calcule impuestos usando
            // product.tax_rate legacy sin que el evento saving() lo sobreescriba.
            if ($item->tax_amount !== null && $item->tax_amount > 0 && $item->tax_rate_snapshot !== null) {
                return; // Tax ya calculado - preservar snapshot histórico
            }
            
            // Calcular impuesto por línea (fallback)
            $tax = null;
            
            if ($item->product_id && $item->product) {
                // Caso 1: Hay producto asociado → usar su impuesto efectivo
                $tax = $item->product->getEffectiveTax();
                
                // FALLBACK LEGACY: si getEffectiveTax() retorna null (no hay tax_id
                // ni default), pero el producto tiene tax_rate legacy, usarlo.
                // Esto mantiene compatibilidad con productos antiguos que solo
                // tienen tax_rate configurado sin tax_id.
                if (!$tax && $item->product->tax_rate !== null && $item->product->tax_rate > 0) {
                    $item->tax_amount = round($item->subtotal * ((float) $item->product->tax_rate / 100), 2);
                    $item->tax_rate_snapshot = (float) $item->product->tax_rate;
                    $item->tax_name_snapshot = null; // Legacy no tiene nombre
                    return;
                }
            } else {
                // Caso 2: Sin producto (item custom, combo, etc.) → usar Tax default de la empresa
                $tax = \Modules\Tax\Domain\Entities\Tax::where('company_id', $item->company_id)
                    ->where('is_default', true)
                    ->where('is_active', true)
                    ->first();
            }
            
            if ($tax) {
                $item->tax_amount = $tax->calculate($item->subtotal, $item->quantity);
                $item->tax_rate_snapshot = $tax->effectiveRate();
                $item->tax_name_snapshot = $tax->name;
            } else {
                // Sin impuesto configurado
                $item->tax_amount = 0;
                $item->tax_rate_snapshot = null;
                $item->tax_name_snapshot = null;
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