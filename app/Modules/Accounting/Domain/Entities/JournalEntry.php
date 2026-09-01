<?php

namespace Modules\Accounting\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Domain\ValueObjects\ReferenceType;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;

/**
 * Asiento contable.
 * 
 * Representa una transacción contable completa con múltiples líneas (LedgerEntry).
 * Cada asiento debe estar balanceado: SUM(debits) == SUM(credits).
 * 
 * Tiene una referencia al documento fuente que lo originó (Payment, Refund, etc.).
 */
class JournalEntry extends Model
{
    use HasUuid, BelongsToTenant;

    protected $fillable = [
        'company_id',
        'branch_id',
        'journal_entry_number',
        'entry_date',
        'reference_type',
        'reference_id',
        'description',
        'user_id',
    ];

    protected $casts = [
        'reference_type' => ReferenceType::class,
        'entry_date' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Domain\Entities\User::class);
    }

    /**
     * Verifica si el asiento está balanceado.
     */
    public function isBalanced(): bool
    {
        $debits = $this->ledgerEntries()->sum('debit_amount');
        $credits = $this->ledgerEntries()->sum('credit_amount');

        return abs($debits - $credits) < 0.01; // Tolerancia por redondeo
    }

    /**
     * Calcula el total de débitos del asiento.
     */
    public function totalDebits(): float
    {
        return (float) $this->ledgerEntries()->sum('debit_amount');
    }

    /**
     * Calcula el total de créditos del asiento.
     */
    public function totalCredits(): float
    {
        return (float) $this->ledgerEntries()->sum('credit_amount');
    }

    public function scopeByReference($query, ReferenceType $type, int $referenceId)
    {
        return $query->where('reference_type', $type)
                     ->where('reference_id', $referenceId);
    }

    public function scopeByDateRange($query, $from, $to)
    {
        return $query->whereBetween('entry_date', [$from, $to]);
    }
}
