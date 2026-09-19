<?php

namespace Modules\Orders\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use App\Shared\Domain\Traits\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Sync\Domain\ValueObjects\SyncStatus;
use Modules\Tables\Domain\Entities\RestaurantTable;

class Order extends Model
{

    protected $casts = [
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'discount_amount' => 'integer',
        'tip_amount' => 'integer',
        'grand_total' => 'integer',
        'amount_due' => 'integer',
        'paid_amount' => 'integer',
        'remaining_amount' => 'integer',
    ];

    use HasFactory;
    use HasUuid;
    use Syncable;
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'branch_id',
        'order_number',
        'type',
        'fulfillment_channel',
        'status',
        'table_id',
        'waiter_id',
        'assigned_cook_id',
        'priority',
        'cashier_id',
        'subtotal',
        'subtotal_gross',
        'net_amount',
        'tax_amount',
        'discount_amount',
        'tip_amount',
        'total',
        'amount_due',
        'notes',
        'customer_name',
        'customer_phone',
        'pickup_at',
        'delivery_address',
        'delivery_notes',
        'confirmed_at',
        'served_at',
        'picked_up_at',
        'dispatched_at',
        'delivered_at',
        'paid_at',
        'closed_at',
        'cancelled_at',
        'cancellation_reason',
        'sync_status',
        'version',
        'last_synced_at',
        'offline_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'fulfillment_channel' => \Modules\Orders\Domain\ValueObjects\FulfillmentChannel::class,
            'status' => OrderStatus::class,
            'sync_status' => SyncStatus::class,
            'version' => 'integer',
            'last_synced_at' => 'datetime',
            'pickup_at' => 'datetime',
        'priority' => \Modules\Orders\Domain\ValueObjects\OrderPriority::class,
            'subtotal' => 'integer',
            'subtotal_gross' => 'integer',
            'net_amount' => 'integer',
            'tax_amount' => 'integer',
            'discount_amount' => 'integer',
            'tip_amount' => 'integer',
            'total' => 'integer',
            'amount_due' => 'integer',
            'confirmed_at' => 'datetime',
            'served_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'paid_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    // ============================================
    // Relaciones
    // ============================================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class);
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // ============================================
    // Scopes de consulta
    // ============================================

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [
            OrderStatus::CLOSED,
            OrderStatus::CANCELLED,
        ]);
    }

    public function scopeInKitchenQueue($query)
    {
        return $query->whereIn('status', [
            OrderStatus::CONFIRMED,
            OrderStatus::PREPARING,
        ]);
    }

    public function scopeAwaitingPayment($query)
    {
        return $query->where('status', OrderStatus::SERVED);
    }

    public function scopeForTable($query, int $tableId)
    {
        return $query->where('table_id', $tableId)->active();
    }

    // ============================================
    // Métodos de negocio
    // ============================================

    public function isEditable(): bool
    {
        return $this->status === OrderStatus::DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function requiresTable(): bool
    {
        return $this->type->requiresTable();
    }

    public function hasItems(): bool
    {
        return $this->items()->exists();
    }

    /**
     * Recalcula totales del pedido usando modelo BRUTO (ADR-011).
     * 
     * Fórmula:
     * - subtotal_gross = SUM(items.subtotal)  [IVA incluido]
     * - net_amount = (int) round(subtotal_gross / 1.19)
     * - tax_amount = subtotal_gross - net_amount
     * - grand_total = subtotal_gross - discount_amount
     * - amount_due = grand_total + tip_amount
     */
    public function recalculateTotals(): void
    {
        // Calcular subtotal_gross desde items (IVA incluido)
        $this->subtotal_gross = $this->items()->sum('subtotal');
        
        // Calcular net_amount (bruto / 1.19)
        $this->net_amount = (int) round($this->subtotal_gross / 1.19);
        
        // Calcular tax_amount (bruto - neto)
        $this->tax_amount = (int) ($this->subtotal_gross - $this->net_amount);
        
        // Calcular grand_total (bruto - descuento)
        $grandTotal = $this->subtotal_gross - ($this->discount_amount ?? 0);
        
        // Calcular amount_due (grand_total + propina)
        $this->amount_due = $grandTotal + ($this->tip_amount ?? 0);
        
        // Mantener campos legacy para compatibilidad temporal
        $this->subtotal = $this->subtotal_gross;
        $this->total = $grandTotal;
    }

    /**
     * Cocinero asignado al pedido.
     */
    public function assignedCook(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_cook_id');
    }

    /**
     * Pagos asociados al pedido.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(\Modules\Payments\Domain\Entities\Payment::class);
    }

    /**
     * Sub-cuentas (Split Bill) del pedido.
     */
    public function bills(): HasMany
    {
        return $this->hasMany(\Modules\Payments\Domain\Entities\Bill::class);
    }
}
