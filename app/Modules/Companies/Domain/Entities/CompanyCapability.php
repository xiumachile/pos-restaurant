<?php

namespace Modules\Companies\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Companies\Domain\ValueObjects\CapabilityKey;

class CompanyCapability extends Model
{
    protected $fillable = [
        'company_id',
        'capability_key',
        'is_enabled',
        'settings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Relación: capability pertenece a una empresa.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Obtiene el CapabilityKey como enum.
     */
    public function getKey(): CapabilityKey
    {
        return CapabilityKey::from($this->capability_key);
    }

    /**
     * Verifica si el capability está habilitado.
     */
    public function isEnabled(): bool
    {
        return $this->is_enabled;
    }

    /**
     * Habilita el capability.
     */
    public function enable(): self
    {
        $this->update(['is_enabled' => true]);
        return $this;
    }

    /**
     * Deshabilita el capability.
     */
    public function disable(): self
    {
        $this->update(['is_enabled' => false]);
        return $this;
    }

    /**
     * Obtiene un setting específico.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Actualiza un setting específico.
     */
    public function setSetting(string $key, mixed $value): self
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->update(['settings' => $settings]);
        return $this;
    }
}
