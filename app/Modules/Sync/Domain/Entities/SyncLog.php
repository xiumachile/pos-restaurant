<?php

namespace Modules\Sync\Domain\Entities;

use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Sync\Domain\ValueObjects\SyncAction;

/**
 * Registro de auditoría de sincronización.
 * 
 * Registra cada operación de sincronización exitosa o fallida
 * para fines de auditoría y debugging.
 */
class SyncLog extends Model
{
    use HasUuid;

    protected $table = 'sync_log';

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'branch_id',
        'sync_session_id',
        'direction',
        'entity_type',
        'entity_id',
        'entity_uuid',
        'action',
        'result',
        'conflict_data',
        'error_message',
        'duration_ms',
        'metadata',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => SyncAction::class,
            'conflict_data' => 'array',
            'metadata' => 'array',
            'duration_ms' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopePushes($query)
    {
        return $query->where('direction', 'push');
    }

    public function scopePulls($query)
    {
        return $query->where('direction', 'pull');
    }

    public function scopeSuccess($query)
    {
        return $query->where('result', 'success');
    }

    public function scopeConflicts($query)
    {
        return $query->where('result', 'conflict');
    }

    public function scopeErrors($query)
    {
        return $query->where('result', 'error');
    }

    public function scopeForSession($query, string $sessionId)
    {
        return $query->where('sync_session_id', $sessionId);
    }
}
