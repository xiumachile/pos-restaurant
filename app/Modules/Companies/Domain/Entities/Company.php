<?php

namespace Modules\Companies\Domain\Entities;

use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Companies\Domain\ValueObjects\CapabilityKey;

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
        return $this->hasMany(\Modules\Branches\Domain\Entities\Branch::class);
    }

    /**
     * Relación: una empresa tiene muchos usuarios.
     */
    public function users(): HasMany
    {
        return $this->hasMany(\Modules\Identity\Domain\Entities\User::class);
    }

    /**
     * Relación: una empresa tiene muchas capabilities.
     */
    public function capabilities(): HasMany
    {
        return $this->hasMany(CompanyCapability::class);
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

    /**
     * Verifica si la empresa tiene un capability habilitado.
     */
    public function hasCapability(string|CapabilityKey $capabilityKey): bool
    {
        $key = $capabilityKey instanceof CapabilityKey 
            ? $capabilityKey->value 
            : $capabilityKey;

        $cacheKey = "company:{$this->id}:capabilities";
        $ttl = 300; // 5 minutos

        // Obtener capabilities del cache o de la base de datos
        $capabilities = cache()->remember($cacheKey, $ttl, function () {
            return $this->capabilities()
                ->where('is_enabled', true)
                ->pluck('capability_key')
                ->toArray();
        });

        return in_array($key, $capabilities, true);
    }

    /**
     * Invalida el cache de capabilities de esta empresa.
     */
    public function invalidateCapabilitiesCache(): void
    {
        $cacheKey = "company:{$this->id}:capabilities";
        cache()->forget($cacheKey);
    }

    /**
     * Obtiene un capability específico.
     */
    public function getCapability(string|CapabilityKey $capabilityKey): ?CompanyCapability
    {
        $key = $capabilityKey instanceof CapabilityKey 
            ? $capabilityKey->value 
            : $capabilityKey;

        return $this->capabilities()
            ->where('capability_key', $key)
            ->first();
    }

    /**
     * Obtiene todas las capabilities habilitadas.
     */
    public function enabledCapabilities(): array
    {
        return $this->capabilities()
            ->where('is_enabled', true)
            ->pluck('capability_key')
            ->toArray();
    }
}
