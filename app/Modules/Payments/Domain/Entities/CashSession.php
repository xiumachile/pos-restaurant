<?php

namespace Modules\Payments\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Cashier\Domain\Entities\CashRegister;
use Modules\Cashier\Domain\Entities\CashMovement;
use Modules\Cashier\Domain\Entities\CashCount;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;

class CashSession extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'user_id',
        'register_id',
        'session_number',
        'status',
        'opening_amount',
        'closing_amount',
        'expected_amount',
        'difference',
        'opening_notes',
        'closing_notes',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'status' => CashSessionStatus::class,
        'opening_amount' => 'integer',
        'closing_amount' => 'integer',
        'expected_amount' => 'integer',
        'difference' => 'integer',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'register_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'cash_session_id');
    }

    public function counts(): HasMany
    {
        return $this->hasMany(CashCount::class, 'cash_session_id');
    }

    /**
     * Scope: sesiones abiertas.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', CashSessionStatus::OPEN);
    }

    /**
     * Verifica si la sesión puede recibir pagos.
     */
    public function canReceivePayments(): bool
    {
        return $this->status->canReceivePayments();
    }

    /**
     * Calcula el monto esperado basado en los pagos registrados.
     */
    /**
     * Calcula el monto esperado BRUTO (informativo para dashboard).
     * Incluye propinas porque asume que aún están en caja.
     */
    /**
     * Calcula el balance esperado de efectivo con opciones configurables.
     * 
     * @param bool $includeMovements Incluir movimientos de caja (retiros/depósitos)
     * @param bool $onlyCashTipsPaidOut Solo descontar propinas pagadas en efectivo
     * @return int
     */
    private function calculateExpectedBalanceInternal(
        bool $includeMovements = true,
        bool $onlyCashTipsPaidOut = false
    ): int {  // Hallazgo 06: CLP siempre es int
        // ADR-011 FIX: Filtrar por method_code case-insensitive
        // Los payments se guardan con method_code = PaymentMethod.code (minúsculas)
        $cashSales = (int) $this->payments()  // ADR-018
            ->where('status', 'completed')
            ->whereRaw('LOWER(method_code) = ?', ['cash'])
            ->sum('amount');
        
        $cashTips = (int) $this->payments()  // ADR-018
            ->where('status', 'completed')
            ->whereRaw('LOWER(method_code) = ?', ['cash'])
            ->sum('tip_amount');
        
        $brutExpected = (int) $this->opening_amount + $cashSales + $cashTips;  // ADR-018: todos int
        
        // Calcular propinas pagadas
        $tipPayoutQuery = \Modules\Cashier\Domain\Entities\TipPayout::where('cash_session_id', $this->id)
            ->valid();
        
        if ($onlyCashTipsPaidOut) {
            $tipPayoutQuery->where('payment_method', 'cash');
        }
        
        $tipsPaidOut = (int) $tipPayoutQuery->sum('amount');
        
        // Calcular impacto de movimientos (si se solicita)
        $movementsImpact = 0;
        if ($includeMovements) {
            $movementsImpact = $this->movements()
                ->get()
                ->sum(fn($m) => $m->balanceImpact());
        }
        
        return (int) ($brutExpected - $tipsPaidOut + $movementsImpact);  // Hallazgo 06: sin round() innecesario
    }

    public function calculateExpectedAmount(): int  // ADR-018: CLP entero
    {
        $totalPayments = (int) $this->payments()
            ->where('status', 'completed')
            ->sum('total_amount');  // amount + tip_amount

        return (int) $this->opening_amount + $totalPayments;
    }

    /**
     * Calcula el monto esperado NETO (para cierre de caja).
     * 
     * Lógica unificada con frontend:
     * 1. Calcula BRUTO: opening + ventas efectivo + propinas efectivo
     * 2. Resta TODAS las propinas entregadas (incluye tarjeta/transferencia
     *    que se entregan en efectivo según política)
     * 
     * Esto garantiza consistencia entre lo que muestra el wizard y lo que
     * guarda el backend.
     */
    /**
     * Calcula el monto esperado al cierre considerando la política de propinas.
     * 
     * Lógica (igual que Z-Report y calculateExpectedCashBalance):
     * - BRUTO = inicial + ventas_efectivo + propinas_efectivo
     * - SALIDAS = solo propinas entregadas FÍSICAMENTE (payment_method='cash')
     * - Las propinas con payment_method='payroll' son registros contables
     *   que NO representan salidas físicas de la caja
     * 
     * Según política:
     * - cash_payout: todas las propinas salen físicamente (payment_method='cash')
     * - mixed: efectivo sale físicamente, tarjeta/transfer va a nómina
     * - payroll: todo va a nómina (nada sale físicamente)
     */
    public function calculateExpectedAmountForClose(): int  // ADR-018: CLP entero
    {
        // Cierre de caja: balance neto con movimientos, solo propinas en efectivo
        return $this->calculateExpectedBalanceInternal(
            includeMovements: true,
            onlyCashTipsPaidOut: true
        );
    }

    /**
     * Calcula el total de propinas pendientes de entregar en esta sesión.
     */
    public function calculatePendingTips(): int  // ADR-018: CLP entero
    {
        $totalTips = (int) $this->payments()  // ADR-018
            ->where('status', 'completed')
            ->where('tip_amount', '>', 0)
            ->sum('tip_amount');

        $paidOut = (int) \Modules\Cashier\Domain\Entities\TipPayout::where('cash_session_id', $this->id)  // ADR-018
            ->valid()
            ->sum('amount');

        return (int) ($totalTips - $paidOut);  // Hallazgo 06: sin round()
    }

    /**
     * Calcula el balance actual de la sesión considerando movimientos.
     *
     * @deprecated Hallazgo 06: Usar getCashBalance() para efectivo físico
     *             o getTotalSalesBalance() para total de ventas.
     *             Este método mezcla todos los métodos de pago (cash/card/transfer)
     *             lo que genera confusión sobre cuánto dinero FÍSICO hay en caja.
     */
    public function calculateCurrentBalance(): int  // ADR-018: CLP entero
    {
        $opening = (int) $this->opening_amount;
        
        // Sumar todos los pagos completados de esta sesión
        // (en el futuro podríamos filtrar por método de pago si es necesario)
        $paymentsTotal = (int) $this->payments()
            ->where('status', 'completed')
            ->sum('total_amount');
        
        $movementsImpact = $this->movements()
            ->get()
            ->sum(fn($m) => $m->balanceImpact());
        
        return $opening + $paymentsTotal + $movementsImpact;  // Sin round() con enteros
    }

    /**
     * Verifica si se ha excedido el monto máximo (requiere retiro).
     */
    public function exceedsMaxAmount(int $maxAmount = 500000): bool
    {
        // Hallazgo 06: comparar contra EFECTIVO FÍSICO, no total de ventas
        return $this->getCashBalance() > $maxAmount;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════
     * MÉTODOS DE BALANCE INEQUÍVOCOS (Hallazgo 06 - P1)
     * ═══════════════════════════════════════════════════════════════════════
     * Separación clara entre efectivo físico y ventas totales.
     * Nunca usar un método genérico para representar efectivo.
     */

    /**
     * Ventas en EFECTIVO (amount + tip_amount, solo método cash).
     * Representa dinero físico que entra a la caja.
     */
    public function getCashSales(): int
    {
        $cashAmount = (int) $this->payments()
            ->where('status', 'completed')
            ->whereRaw('LOWER(method_code) = ?', ['cash'])
            ->sum('amount');

        $cashTips = (int) $this->payments()
            ->where('status', 'completed')
            ->whereRaw('LOWER(method_code) = ?', ['cash'])
            ->sum('tip_amount');

        return $cashAmount + $cashTips;
    }

    /**
     * Ventas con TARJETA (amount + tip_amount, métodos de tipo card).
     * NO afecta el efectivo físico en caja.
     */
    public function getCardSales(): int
    {
        $cardAmount = (int) $this->payments()
            ->where('status', 'completed')
            ->whereRaw('LOWER(method_code) IN (?, ?, ?)', ['card', 'debit_card', 'credit_card'])
            ->sum('amount');

        $cardTips = (int) $this->payments()
            ->where('status', 'completed')
            ->whereRaw('LOWER(method_code) IN (?, ?, ?)', ['card', 'debit_card', 'credit_card'])
            ->sum('tip_amount');

        return $cardAmount + $cardTips;
    }

    /**
     * Ventas por TRANSFERENCIA (amount + tip_amount).
     * NO afecta el efectivo físico en caja.
     */
    public function getTransferSales(): int
    {
        $transferAmount = (int) $this->payments()
            ->where('status', 'completed')
            ->whereRaw('LOWER(method_code) IN (?, ?)', ['transfer', 'bank_transfer'])
            ->sum('amount');

        $transferTips = (int) $this->payments()
            ->where('status', 'completed')
            ->whereRaw('LOWER(method_code) IN (?, ?)', ['transfer', 'bank_transfer'])
            ->sum('tip_amount');

        return $transferAmount + $transferTips;
    }

    /**
     * TOTAL de ventas (todos los métodos de pago).
     * Informativo para dashboard, NO representa efectivo físico.
     * Reemplaza semánticamente al ambiguo calculateCurrentBalance().
     */
    public function getTotalSalesBalance(): int
    {
        $opening = (int) $this->opening_amount;

        $paymentsTotal = (int) $this->payments()
            ->where('status', 'completed')
            ->sum('total_amount');

        $movementsImpact = $this->movements()
            ->get()
            ->sum(fn($m) => $m->balanceImpact());

        return $opening + $paymentsTotal + $movementsImpact;
    }

    /**
     * EFECTIVO FÍSICO REAL en caja.
     *
     * Este es el método correcto para arqueos y alertas de retiro.
     * Solo incluye:
     * - Apertura inicial
     * - Ventas en efectivo (amount + tip)
     * - Movimientos de caja (retiros/depósitos)
     * - Propinas pagadas físicamente (descuento)
     *
     * NO incluye tarjeta/transferencia (ese dinero no está en la caja física).
     */
    public function getCashBalance(): int
    {
        $opening = (int) $this->opening_amount;
        $cashSales = $this->getCashSales();

        $movementsImpact = $this->movements()
            ->get()
            ->sum(fn($m) => $m->balanceImpact());

        // Propinas pagadas físicamente (cash_payout o mixed con cash)
        $cashTipsPaidOut = (int) \Modules\Cashier\Domain\Entities\TipPayout::where('cash_session_id', $this->id)
            ->valid()
            ->where('payment_method', 'cash')
            ->sum('amount');

        return $opening + $cashSales + $movementsImpact - $cashTipsPaidOut;
    }


    /**
     * Calcula el balance esperado de efectivo considerando todos los factores.
     * Este método unifica la lógica correcta para arqueos y reportes:
     * - Solo considera ventas en efectivo (CASH)
     * - Incluye propinas en efectivo
     * - Resta propinas entregadas físicamente
     * - Incluye impacto de movimientos de caja (retiros/depósitos)
     */
    public function calculateExpectedCashBalance(): int  // ADR-018: CLP entero
    {
        // Balance completo: con movimientos y todas las propinas
        return $this->calculateExpectedBalanceInternal(
            includeMovements: true,
            onlyCashTipsPaidOut: false
        );
    }

}
