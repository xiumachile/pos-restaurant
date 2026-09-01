<?php

namespace Modules\Accounting\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Domain\ValueObjects\AccountType;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;

/**
 * Cuenta contable.
 * 
 * Representa una cuenta en el plan contable de la empresa.
 * Ejemplos: Cash (efectivo en caja), Bank (cuenta bancaria),
 * Revenue (ingresos), TaxPayable (IVA por pagar), etc.
 */
class Account extends Model
{
    use HasUuid, BelongsToTenant;

    protected $fillable = [
        'company_id',
        'branch_id',
        'code',
        'name',
        'type',
        'description',
        'is_active',
        'parent_id',
    ];

    protected $casts = [
        'type' => AccountType::class,
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * Calcula el balance actual de la cuenta.
     * 
     * Para cuentas con saldo normal débito (Asset, Expense):
     *   Balance = SUM(debits) - SUM(credits)
     * 
     * Para cuentas con saldo normal crédito (Liability, Equity, Revenue):
     *   Balance = SUM(credits) - SUM(debits)
     */
    public function balance(): float
    {
        $debits = $this->ledgerEntries()->sum('debit_amount');
        $credits = $this->ledgerEntries()->sum('credit_amount');

        return $this->type->normalBalance() === 'debit'
            ? $debits - $credits
            : $credits - $debits;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, AccountType $type)
    {
        return $query->where('type', $type);
    }
}
