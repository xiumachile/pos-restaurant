<?php

namespace Modules\Accounting\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de asiento contable.
 * 
 * Representa una línea individual de un JournalEntry.
 * Cada línea afecta a una cuenta con un débito o crédito.
 * 
 * Regla: una línea tiene SOLO débito O SOLO crédito (no ambos).
 */
class LedgerEntry extends Model
{
    use HasUuid, BelongsToTenant;

    protected $fillable = [
        'company_id',
        'branch_id',
        'journal_entry_id',
        'account_id',
        'debit_amount',
        'credit_amount',
        'description',
    ];

    protected $casts = [
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\Modules\Companies\Domain\Entities\Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\Modules\Branches\Domain\Entities\Branch::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Verifica si esta línea es un débito.
     */
    public function isDebit(): bool
    {
        return $this->debit_amount > 0 && $this->credit_amount == 0;
    }

    /**
     * Verifica si esta línea es un crédito.
     */
    public function isCredit(): bool
    {
        return $this->credit_amount > 0 && $this->debit_amount == 0;
    }

    public function scopeDebits($query)
    {
        return $query->where('debit_amount', '>', 0);
    }

    public function scopeCredits($query)
    {
        return $query->where('credit_amount', '>', 0);
    }
}
