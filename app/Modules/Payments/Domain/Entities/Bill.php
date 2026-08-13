<?php

namespace Modules\Payments\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Orders\Domain\Entities\Order;
use Modules\Payments\Domain\ValueObjects\BillStatus;
use Modules\Payments\Domain\ValueObjects\BillType;

class Bill extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'order_id',
        'bill_number',
        'type',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'tip_amount',
        'total',
        'paid_amount',
        'remaining_amount',
        'status',
        'guest_count',
        'item_ids',
    ];

    protected $casts = [
        'type' => BillType::class,
        'status' => BillStatus::class,
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tip_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'item_ids' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scope: bills pendientes de pago.
     */
    public function scopePayable($query)
    {
        return $query->whereIn('status', [BillStatus::OPEN, BillStatus::PARTIAL]);
    }

    /**
     * Verifica si el bill puede recibir pagos.
     */
    public function isPayable(): bool
    {
        return $this->status->isPayable();
    }

    /**
     * Verifica si está completamente pagado.
     */
    public function isFullyPaid(): bool
    {
        return (float) $this->remaining_amount <= 0;
    }

    /**
     * Registra un pago parcial y actualiza los montos.
     */
    public function registerPaymentAmount(float $amount): void
    {
        $this->paid_amount = (float) $this->paid_amount + $amount;
        $this->remaining_amount = max(0, (float) $this->total - (float) $this->paid_amount);

        if ($this->isFullyPaid()) {
            $this->status = BillStatus::PAID;
        } elseif ((float) $this->paid_amount > 0) {
            $this->status = BillStatus::PARTIAL;
        }

        $this->save();
    }

    /**
     * Genera el número de bill basado en el order_number.
     */
    public static function generateBillNumber(string $orderNumber, int $sequence): string
    {
        return "{$orderNumber}-{$sequence}";
    }
}
