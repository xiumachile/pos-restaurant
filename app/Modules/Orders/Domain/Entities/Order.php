<?php

namespace Modules\Orders\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Tables\Domain\Entities\RestaurantTable;

class Order extends Model
{
    use HasFactory;
    use HasUuid;
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'branch_id',
        'order_number',
        'type',
        'status',
        'table_id',
        'waiter_id',
        'assigned_cook_id',
        'priority',
        'cashier_id',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'notes',
        'confirmed_at',
        'served_at',
        'paid_at',
        'closed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
        'priority' => \Modules\Orders\Domain\ValueObjects\OrderPriority::class,
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'served_at' => 'datetime',
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

    public function recalculateTotals(): void
    {
        $this->subtotal = $this->items()->sum('subtotal');
        // IVA 19% (configurable en el futuro)
        $this->tax_amount = round($this->subtotal * 0.19, 2);
        $this->total = $this->subtotal + $this->tax_amount - $this->discount_amount;
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
