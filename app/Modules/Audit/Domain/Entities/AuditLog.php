<?php

namespace Modules\Audit\Domain\Entities;

use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;

/**
 * Registro de auditoría inmutable.
 * 
 * Principio arquitectónico #8: Todas las acciones críticas
 * (cancelaciones, descuentos, aperturas de cajón, cambios de precio)
 * deben registrarse de forma inmutable.
 * 
 * Esta entidad NO permite UPDATE ni DELETE por diseño.
 */
class AuditLog extends Model
{
    use HasUuid;

    protected $fillable = [
        'company_id',
        'branch_id',
        'user_id',
        'user_name',
        'action',
        'entity_type',
        'entity_id',
        'entity_uuid',
        'payload',
        'changes',
        'reason',
        'ip_address',
        'user_agent',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'changes' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * Prevenir actualizaciones (inmutabilidad).
     */
    public function update(array $attributes = [], array $options = [])
    {
        throw new \RuntimeException('AuditLog es inmutable: no se puede actualizar.');
    }

    /**
     * Prevenir eliminaciones (inmutabilidad).
     */
    public function delete()
    {
        throw new \RuntimeException('AuditLog es inmutable: no se puede eliminar.');
    }

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

    // ============================================
    // Scopes
    // ============================================

    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeForEntity($query, string $entityType, int $entityId)
    {
        return $query->where('entity_type', $entityType)
            ->where('entity_id', $entityId);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeBetween($query, $start, $end)
    {
        return $query->whereBetween('occurred_at', [$start, $end]);
    }
}
