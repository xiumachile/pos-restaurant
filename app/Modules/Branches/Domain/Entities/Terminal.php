<?php

namespace Modules\Branches\Domain\Entities;

use App\Shared\Domain\Traits\HasUuid;
use App\Shared\Domain\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;

class Terminal extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'code',
        'name',
        'locale',
        'mac_address',
        'is_kds',
        'is_pos',
        'is_active',
    ];

    protected $casts = [
        'is_kds' => 'boolean',
        'is_pos' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Relación: una terminal pertenece a una empresa.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relación: una terminal pertenece a una sucursal.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Resolver locale según jerarquía (sección 9.3 de arquitectura).
     * Terminal > User > Branch > Company
     */
    public function resolveLocale(?User $user = null): string
    {
        return $this->locale
            ?? $user?->locale
            ?? $this->branch?->effectiveLocale()
            ?? $this->company->effectiveLocale();
    }
}
