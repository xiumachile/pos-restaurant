<?php

namespace Modules\Payments\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Accounting\Domain\Entities\JournalEntry;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\ValueObjects\RefundStatus;

/**
 * Reembolso de un pago.
 *
 * Puede ser total (reembolsa el 100%) o parcial (reembolsa una parte).
 * La suma de todos los refunds completados de un payment NO puede exceder
 * el monto total del payment original.
 *
 * Cada refund COMPLETED genera un JournalEntry de reversa en el ledger.
 */
class Refund extends Model
{
    use HasUuid, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'payment_id',
        'refund_number',
        'amount',
        'status',
        'reason',
        'processed_at',
        'processed_by',
        'journal_entry_id',
        'notes',
        'idempotency_key',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => RefundStatus::class,
        'processed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', RefundStatus::COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->where('status', RefundStatus::PENDING);
    }

    /**
     * Calcula el total reembolsado de un payment (excluyendo este refund si aún no está completed).
     */
    public static function totalRefundedFor(int $paymentId, ?int $excludeRefundId = null): float
    {
        $query = self::where('payment_id', $paymentId)
            ->where('status', RefundStatus::COMPLETED);

        if ($excludeRefundId) {
            $query->where('id', '!=', $excludeRefundId);
        }

        return (float) $query->sum('amount');
    }
}
