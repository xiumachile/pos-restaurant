<?php

namespace Modules\Sync\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Sync\Domain\ValueObjects\SyncAction;

class SyncQueue extends Model
{
    use BelongsToTenant;
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
        'last_error_code',
        'last_attempt_at',
        'next_attempt_at',
        'processing_started_at',
        'lease_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => SyncAction::class,
            'payload' => 'array',
        'conflict_data' => 'array',
            'version' => 'integer',
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'lease_expires_at' => 'datetime',
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

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
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
            ->where('attempts', '<', 5);
    }

    /**
     * O-03 FIX: Scope para encontrar jobs atrapados en estado 'processing'
     * cuyo lease haya expirado.
     */
    public function scopeStuck($query)
    {
        return $query->where('status', 'processing')
            ->where('lease_expires_at', '<', now());
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }
}
