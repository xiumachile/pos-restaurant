<?php

namespace Modules\Payments\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use App\Shared\Domain\Traits\Syncable;
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
    use Syncable;
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
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'discount_amount' => 'integer',
        'tip_amount' => 'integer',
        'total' => 'integer',
        'paid_amount' => 'integer',
        'remaining_amount' => 'integer',
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
    /**
     * ADR-018: Comparación entera directa, sin epsilon.
     */
    public function isFullyPaid(): bool
    {
        return (int) $this->remaining_amount <= 0;
    }

    /**
     * Registra un pago parcial y actualiza los montos.
     */
    /**
     * Registra un pago parcial y actualiza los montos.
     * ADR-018: Aritmética entera, sin floats, sin round().
     * Invariant: paid_amount + remaining_amount = total (exacto).
     */
    public function registerPaymentAmount(int $amount): void
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Payment amount must be non-negative');
        }

        $this->paid_amount = (int) $this->paid_amount + $amount;
        $this->remaining_amount = max(0, (int) $this->total - (int) $this->paid_amount);

        if ($this->isFullyPaid()) {
            $this->status = BillStatus::PAID;
        } elseif ((int) $this->paid_amount > 0) {
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
