<?php

namespace Modules\Cashier\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\Entities\CashSession;

/**
 * Registro de entrega de propinas a garzones.
 */
class TipPayout extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'cash_session_id',
        'processed_by',
        'waiter_id',
        'amount',
        'payment_method',
        'policy_type',
        'notes',
        'is_voided',
        'voided_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_voided' => 'boolean',
        'voided_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scope: entregas válidas (no anuladas).
     */
    public function scopeValid($query)
    {
        return $query->where('is_voided', false);
    }

    /**
     * Scope: entregas en efectivo (las que salen de caja).
     */
    public function scopeCash($query)
    {
        return $query->where('payment_method', 'cash');
    }
}
