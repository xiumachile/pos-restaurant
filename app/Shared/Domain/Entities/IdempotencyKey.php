<?php

namespace App\Shared\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Domain\Entities\User;

/**
 * Registro de idempotencia para prevenir procesamiento duplicado.
 * 
 * Principio arquitectónico #7: Todas las mutaciones de venta/pago
 * deben ser idempotentes usando el header Idempotency-Key.
 */
class IdempotencyKey extends Model
{
    protected $fillable = [
        'key',
        'request_hash',
        'response_body',
        'response_code',
        'user_id',
        'endpoint',
        'expires_at',
    ];

    protected $casts = [
        'response_body' => 'array',
        'response_code' => 'integer',
        'expires_at' => 'datetime',
    ];

    protected $attributes = [
        'response_code' => null,
    ];

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
}
