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

    /**
     * Siembra las cuentas contables por defecto para una empresa.
     * Idempotente: si ya existen, no las crea nuevamente.
     *
     * Plan contable por defecto:
     * - 1100 Efectivo en Caja (asset)
     * - 1200 Bancos (asset)
     * - 1300 Por Cobrar Tarjetas (asset)
     * - 1400 Gift Cards por Canjear (asset)
     * - 2100 IVA por Pagar (liability)
     * - 2200 Propinas por Pagar (liability)
     * - 4100 Ingresos por Ventas (revenue)
     * - 4200 Descuentos sobre Ventas (revenue, contra-account)
     * - 5100 Costo de Ventas (expense)
     * - 5200 Gastos Operativos (expense)
     */
    public static function seedDefaultsFor(int $companyId, ?int $branchId = null): void
    {
        $accounts = [
            ['code' => '1100', 'name' => 'Efectivo en Caja', 'type' => AccountType::ASSET, 'description' => 'Efectivo físico en caja'],
            ['code' => '1200', 'name' => 'Bancos', 'type' => AccountType::ASSET, 'description' => 'Cuentas bancarias'],
            ['code' => '1300', 'name' => 'Por Cobrar Tarjetas', 'type' => AccountType::ASSET, 'description' => 'Pagos con tarjeta por liquidar'],
            ['code' => '1400', 'name' => 'Gift Cards por Canjear', 'type' => AccountType::ASSET, 'description' => 'Gift cards emitidas pendientes de canje'],
            ['code' => '2100', 'name' => 'IVA por Pagar', 'type' => AccountType::LIABILITY, 'description' => 'IVA colectado por pagar al SII'],
            ['code' => '2200', 'name' => 'Propinas por Pagar', 'type' => AccountType::LIABILITY, 'description' => 'Propinas colectadas por pagar a garzones'],
            ['code' => '4100', 'name' => 'Ingresos por Ventas', 'type' => AccountType::REVENUE, 'description' => 'Ingresos por ventas de productos/servicios'],
            ['code' => '4200', 'name' => 'Descuentos sobre Ventas', 'type' => AccountType::REVENUE, 'description' => 'Descuentos aplicados (contra-ingreso)'],
            ['code' => '5100', 'name' => 'Costo de Ventas', 'type' => AccountType::EXPENSE, 'description' => 'Costo de los productos vendidos'],
            ['code' => '5200', 'name' => 'Gastos Operativos', 'type' => AccountType::EXPENSE, 'description' => 'Gastos generales del negocio'],
        ];

        foreach ($accounts as $account) {
            // Usar INSERT ... ON CONFLICT DO NOTHING para hacer el seed 100% idempotente
            // y libre de problemas de transacciones abortadas en PostgreSQL.
            //
            // Por qué funciona:
            // - Si la cuenta ya existe: PostgreSQL ignora el INSERT silenciosamente (DO NOTHING)
            // - Si no existe: PostgreSQL hace el INSERT normalmente
            // - Nunca falla con duplicate key (23505) ni transaction aborted (25P02)
            // - Funciona dentro de transacciones anidadas (RefreshDatabase de tests)
            //
            // Nota: Usamos raw SQL porque Eloquent no tiene un método nativo para
            // INSERT ON CONFLICT DO NOTHING que no dispare eventos.
            \Illuminate\Support\Facades\DB::statement("
                INSERT INTO accounts (company_id, code, branch_id, name, type, description, is_active, uuid, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (company_id, code) DO NOTHING
            ", [
                $companyId,
                $account['code'],
                $branchId,
                $account['name'],
                $account['type']->value,
                $account['description'],
                true,
                \Illuminate\Support\Str::uuid()->toString(),
                now(),
                now(),
            ]);
        }
    }

    /**
     * Busca una cuenta por código de empresa.
     * Lanza excepción si no existe.
     */
    public static function findByCodeOrFail(int $companyId, string $code): self
    {
        $account = self::where('company_id', $companyId)
            ->where('code', $code)
            ->first();

        if (!$account) {
            throw new \RuntimeException(
                "Cuenta contable '{$code}' no encontrada para empresa {$companyId}. " .
                "Ejecutar Account::seedDefaultsFor({$companyId}) para crear cuentas por defecto."
            );
        }

        return $account;
    }

}
