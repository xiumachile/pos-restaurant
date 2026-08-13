<?php

namespace Modules\Payments\Domain\Entities;

use App\Shared\Domain\Traits\HasTranslations;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Payments\Domain\ValueObjects\PaymentMethodType;

class PaymentMethod extends Model
{
    use HasUuid;
    use HasTranslations;

    protected $fillable = [
        'company_id',
        'branch_id',
        'code',
        'name_translations',
        'type',
        'icon',
        'max_amount',
        'requires_reference',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'type' => PaymentMethodType::class,
        'max_amount' => 'decimal:2',
        'requires_reference' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected array $translatableFields = ['name_translations'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scope: solo métodos activos.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

/**
     * Verifica si un monto es válido para este método.
     */
    public function acceptsAmount(float $amount): bool
    {
        if ($this->max_amount === null) {
            return true;
        }
        return $amount <= (float) $this->max_amount;
    }

    /**
     * Scope: métodos globales de empresa (branch_id null) o de una sucursal específica.
     * Esto reemplaza BelongsToTenant porque los métodos de pago pueden ser compartidos.
     */
    public function scopeForBranch($query, ?int $branchId)
    {
        return $query->where('company_id', auth()->user()?->company_id)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id');
                if ($branchId) {
                    $q->orWhere('branch_id', $branchId);
                }
            });
    }
}
