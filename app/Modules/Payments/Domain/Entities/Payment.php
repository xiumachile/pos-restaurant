<?php

namespace Modules\Payments\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use App\Shared\Domain\Traits\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Payments\Domain\ValueObjects\PaymentStatus;

class Payment extends Model
{
    use HasUuid;
    use Syncable;
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'order_id',
        'bill_id',
        'cash_session_id',
        'payment_method_id',
        'user_id',
        'payment_number',
        'method_code',
        'amount',
        'tip_amount',
        'total_amount',
        'reference_code',
        'status',
        'idempotency_key',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'status' => PaymentStatus::class,
        'amount' => 'decimal:2',
        'tip_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
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

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: pagos completados.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', PaymentStatus::COMPLETED);
    }

    /**
     * Verifica si el pago fue exitoso.
     */
    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    /**
     * Calcula el total (amount + tip).
     */
    public static function calculateTotal(float $amount, float $tipAmount = 0): float
    {
        return round($amount + $tipAmount, 2);
    }

    /**
     * Genera un número de pago único.
     */
    public static function generatePaymentNumber(string $branchCode): string
    {
        return sprintf('PAY-%s-%s', strtoupper($branchCode), date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)));
    }
}
