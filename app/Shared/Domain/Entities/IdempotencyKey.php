<?php

namespace App\Shared\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;

/**
 * Registro de idempotencia para prevenir procesamiento duplicado.
 * 
 * Principio arquitectónico #7: Todas las mutaciones de venta/pago
 * deben ser idempotentes usando el header Idempotency-Key.
 * 
 * ADR-002: Scoped a tenant (company_id + key = unique).
 * Dos tenants pueden usar la misma key sin colisionar.
 */
class IdempotencyKey extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'branch_id',
        'key',
        'request_hash',
        'response_body',
        'response_code',
        'user_id',
        'endpoint',
        'expires_at',
        'processing_until',
    ];

    protected $casts = [
        'response_body' => 'array',
        'response_code' => 'integer',
        'expires_at' => 'datetime',
        'processing_until' => 'datetime',
    ];

    protected $attributes = [
        'response_code' => null,
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

    /**
     * Verifica si esta key ha expirado.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Verifica si esta key tiene una respuesta cacheada válida.
     */
    public function hasValidResponse(): bool
    {
        return !$this->isExpired() && $this->response_body !== null;
    }

    /**
     * Scope: keys no expiradas.
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope: keys expiradas (para cleanup).
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Limpia keys expiradas (para cron job).
     */
    public static function cleanupExpired(): int
    {
        return self::expired()->delete();
    }
    /**
     * P1-011: Verifica si el lease de procesamiento ha expirado.
     * Esto indica un "zombie lock" (el proceso murió antes de guardar la respuesta).
     */
    public function isProcessingExpired(): bool
    {
        return $this->processing_until && now()->greaterThan($this->processing_until);
    }

    /**
     * P1-011: Toma posesión del claim renovando el lease de procesamiento.
     */
    public function takeOwnership(): void
    {
        $this->update([
            'processing_until' => now()->addSeconds(60), // 60 segundos de lease
        ]);
    }

}