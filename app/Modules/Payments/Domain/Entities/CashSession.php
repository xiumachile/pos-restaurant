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
        'opening_amount' => 'decimal:2',
        'closing_amount' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'difference' => 'decimal:2',
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
    public function calculateExpectedAmount(): float
    {
        $totalPayments = (float) $this->payments()
            ->where('status', 'completed')
            ->sum('total_amount');  // amount + tip_amount

        return (float) $this->opening_amount + $totalPayments;
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
    public function calculateExpectedAmountForClose(): float
    {
        // BRUTO: inicial + ventas efectivo + propinas efectivo
        $cashSales = (float) $this->payments()
            ->where('status', 'completed')
            ->where('method_code', 'CASH')
            ->sum('amount');
        
        $cashTips = (float) $this->payments()
            ->where('status', 'completed')
            ->where('method_code', 'CASH')
            ->sum('tip_amount');
        
        $brutExpected = (float) $this->opening_amount + $cashSales + $cashTips;
        
        // Restar TODAS las propinas entregadas (no solo las de efectivo)
        // Porque las propinas de tarjeta/transferencia también se entregan
        // en efectivo al garzón (según política de propinas)
        $totalTipsPaidOut = (float) \Modules\Cashier\Domain\Entities\TipPayout::where('cash_session_id', $this->id)
            ->valid()
            ->sum('amount');
        
        return round($brutExpected - $totalTipsPaidOut, 2);
    }

    /**
     * Calcula el total de propinas pendientes de entregar en esta sesión.
     */
    public function calculatePendingTips(): float
    {
        $totalTips = (float) $this->payments()
            ->where('status', 'completed')
            ->where('tip_amount', '>', 0)
            ->sum('tip_amount');

        $paidOut = (float) \Modules\Cashier\Domain\Entities\TipPayout::where('cash_session_id', $this->id)
            ->valid()
            ->sum('amount');

        return round($totalTips - $paidOut, 2);
    }

    /**
     * Calcula el balance actual de la sesión considerando movimientos.
     */
    public function calculateCurrentBalance(): float
    {
        $opening = (float) $this->opening_amount;
        
        // Sumar todos los pagos completados de esta sesión
        // (en el futuro podríamos filtrar por método de pago si es necesario)
        $paymentsTotal = (float) $this->payments()
            ->where('status', 'completed')
            ->sum('total_amount');
        
        $movementsImpact = $this->movements()
            ->get()
            ->sum(fn($m) => $m->balanceImpact());
        
        return round($opening + $paymentsTotal + $movementsImpact, 2);
    }

    /**
     * Verifica si se ha excedido el monto máximo (requiere retiro).
     */
    public function exceedsMaxAmount(float $maxAmount = 500000.0): bool
    {
        return $this->calculateCurrentBalance() > $maxAmount;
    }
}
