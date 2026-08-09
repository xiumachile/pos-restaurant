<?php

namespace Modules\Companies\Domain\Entities;

use App\Shared\Domain\Traits\HasUuid;
use App\Shared\Domain\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasUuid;
    use SoftDeletes;
    // Nota: NO usamos BelongsToTenant porque Company es la raíz del tenant

    protected $fillable = [
        'tax_id',
        'legal_name',
        'trade_name',
        'default_locale',
        'fallback_locale',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Relación: una empresa tiene muchas sucursales.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Relación: una empresa tiene muchos usuarios.
     */
    public function users(): HasMany
    {
        return $this->hasMany(\Modules\Identity\Domain\Entities\User::class);
    }

    /**
     * Locale efectivo con fallback.
     */
    public function effectiveLocale(): string
    {
        return $this->default_locale ?? 'es-CL';
    }

    /**
     * Fallback locale.
     */
    public function fallbackLocale(): string
    {
        return $this->fallback_locale ?? 'es-CL';
    }
}
