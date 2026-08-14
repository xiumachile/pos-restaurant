<?php

namespace Modules\Fiscal\Domain\Entities;

use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Companies\Domain\Entities\Company;

/**
 * Certificado digital para firma de DTEs.
 * Almacenado en formato PKCS#12 (.pfx) encriptado.
 */
class DteCertificate extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $table = 'dte_certificates';

    protected $fillable = [
        'company_id',
        'name',
        'serial_number',
        'issuer',
        'certificate_content',
        'holder_rut',
        'holder_name',
        'valid_from',
        'valid_until',
        'environment',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * Atributos por defecto.
     */
    protected $attributes = [
        'is_active' => true,
        'environment' => self::ENV_CERTIFICATION,
    ];

    // Ambientes
    public const ENV_CERTIFICATION = 'certification';
    public const ENV_PRODUCTION = 'production';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Verifica si el certificado está vigente (no vencido).
     */
    public function isValid(): bool
    {
        return $this->is_active 
            && $this->valid_until->isFuture()
            && $this->valid_from->isPast();
    }

    /**
     * Días restantes antes del vencimiento.
     */
    public function daysUntilExpiration(): int
    {
        return max(0, now()->diffInDays($this->valid_until, false));
    }

    /**
     * Verifica si está próximo a vencer (< 30 días).
     */
    public function isExpiringSoon(): bool
    {
        return $this->isValid() && $this->daysUntilExpiration() <= 30;
    }

    /**
     * Marca el certificado como usado (actualiza last_used_at).
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

    public function scopeOfEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }

    public function scopeValid($query)
    {
        return $query->active()
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now());
    }
}
