<?php

namespace Modules\Cashier\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Payments\Domain\Entities\CashSession;

/**
 * Caja registradora física de una sucursal.
 * Representa el hardware (cajón, impresora) donde operan los cajeros.
 */
class CashRegister extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'code',
        'description',
        'opening_amount_default',
        'max_amount',
        'requires_dual_control',
        'printer_id',
        'drawer_serial',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'opening_amount_default' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'requires_dual_control' => 'boolean',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
        'opening_amount_default' => 50000.00,
        'max_amount' => 500000.00,
        'requires_dual_control' => false,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class, 'register_id');
    }

    /**
     * Obtiene la sesión actualmente abierta en esta caja.
     */
    public function currentSession(): ?CashSession
    {
        return $this->sessions()
            ->where('status', 'open')
            ->first();
    }

    /**
     * Verifica si la caja está disponible (activa y sin sesión abierta).
     */
    public function isAvailable(): bool
    {
        return $this->is_active && $this->currentSession() === null;
    }

    /**
     * Verifica si la caja está ocupada (tiene sesión abierta).
     */
    public function isBusy(): bool
    {
        return $this->currentSession() !== null;
    }

    /**
     * Registra el último uso de la caja.
     */
    public function recordUsage(): void
    {
        $this->last_used_at = now();
        $this->save();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->active()
            ->whereDoesntHave('sessions', function ($q) {
                $q->where('status', 'open');
            });
    }
}
