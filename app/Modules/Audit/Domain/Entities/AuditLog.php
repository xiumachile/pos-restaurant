<?php

namespace Modules\Audit\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;

class AuditLog extends Model
{
    use HasUuid;
    use BelongsToTenant;  // FIX P0: Protección cross-tenant

    public $timestamps = false;

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

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'changes' => 'array',
            'occurred_at' => 'datetime',
        ];
    }


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

    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForEntity($query, string $entityType, int $entityId)
    {
        return $query->where('entity_type', $entityType)
                     ->where('entity_id', $entityId);
    }
}
