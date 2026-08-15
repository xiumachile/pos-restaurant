<?php

namespace Modules\Sync\Domain\Entities;

use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Sync\Domain\ValueObjects\SyncAction;

/**
 * Registro en la cola de sincronización.
 * 
 * Cada cambio local (create/update/delete) genera un registro aquí
 * que será procesado por SyncService cuando haya conexión.
 */
class SyncQueue extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $table = 'sync_queue';

    protected $fillable = [
        'company_id',
        'branch_id',
        'entity_type',
        'entity_id',
        'entity_uuid',
        'action',
        'payload',
        'version',
        'attempts',
        'status',
        'error_message',
        'last_attempt_at',
        'next_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => SyncAction::class,
            'payload' => 'array',
            'version' => 'integer',
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'next_attempt_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'attempts' => 0,
        'status' => 'pending',
        'version' => 1,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Obtiene la instancia del modelo al que se refiere este registro.
     */
    public function getEntity(): ?Model
    {
        if (!$this->entity_type || !$this->entity_id) {
            return null;
        }

        if (!class_exists($this->entity_type)) {
            return null;
        }

        return $this->entity_type::find($this->entity_id);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRetryable($query)
    {
        return $query->where('status', 'failed')
            ->where(function ($q) {
                $q->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', now());
            })
            ->where('attempts', '<', 5); // Máximo 5 reintentos
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }
}
