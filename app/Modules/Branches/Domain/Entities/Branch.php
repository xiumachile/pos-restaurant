<?php

namespace Modules\Branches\Domain\Entities;

use App\Shared\Domain\Traits\HasUuid;
use App\Shared\Domain\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Companies\Domain\Entities\Company;

class Branch extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'address',
        'phone',
        'default_locale',
        'tip_percentage_suggested',
        'allow_negative_stock',
        'is_active',
    ];

    protected $casts = [
        'tip_percentage_suggested' => 'decimal:2',
        'allow_negative_stock' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Relación: una sucursal pertenece a una empresa.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relación: una sucursal tiene muchas terminales.
     */
    public function terminals(): HasMany
    {
        return $this->hasMany(\Modules\Branches\Domain\Entities\Terminal::class);
    }

    /**
     * Relación: una sucursal tiene muchos usuarios.
     */
    public function users(): HasMany
    {
        return $this->hasMany(\Modules\Identity\Domain\Entities\User::class);
    }

    /**
     * Locale efectivo con fallback a empresa.
     */
    public function effectiveLocale(): string
    {
        return $this->default_locale ?? $this->company->effectiveLocale();
    }
}
