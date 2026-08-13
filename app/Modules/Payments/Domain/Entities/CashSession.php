<?php

namespace Modules\Payments\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Domain\Entities\Branch;
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
    public function calculateExpectedAmount(): float
    {
        $totalPayments = (float) $this->payments()
            ->where('status', 'completed')
            ->sum('total_amount');

        return (float) $this->opening_amount + $totalPayments;
    }
}
