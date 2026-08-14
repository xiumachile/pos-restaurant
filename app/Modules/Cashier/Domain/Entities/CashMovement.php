<?php

namespace Modules\Cashier\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Cashier\Domain\ValueObjects\MovementType;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\Entities\CashSession;

/**
 * Movimiento de caja (retiro, depósito o ajuste).
 * Registra cualquier movimiento de efectivo fuera de las ventas normales.
 */
class CashMovement extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'cash_session_id',
        'user_id',
        'type',
        'amount',
        'reason',
        'notes',
        'reference_type',
        'reference_id',
        'authorized_by',
        'authorized_at',
        'balance_after',
    ];

    protected $casts = [
        'type' => MovementType::class,
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'authorized_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    /**
     * Verifica si el movimiento está autorizado.
     */
    public function isAuthorized(): bool
    {
        return !is_null($this->authorized_by);
    }

    /**
     * Autoriza el movimiento (requiere supervisor para montos grandes).
     */
    public function authorize(User $authorizer): void
    {
        $this->authorized_by = $authorizer->id;
        $this->authorized_at = now();
        $this->save();
    }

    /**
     * Impacto en el balance (positivo o negativo según tipo).
     */
    public function balanceImpact(): float
    {
        return (float) $this->amount * $this->type->balanceSign();
    }

    public function scopeOfType($query, MovementType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeWithdrawals($query)
    {
        return $query->ofType(MovementType::WITHDRAWAL);
    }

    public function scopeDeposits($query)
    {
        return $query->ofType(MovementType::DEPOSIT);
    }
}
