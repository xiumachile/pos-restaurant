<?php

namespace App\Shared\Domain\Traits;

use Modules\Sync\Domain\ValueObjects\SyncStatus;
use Modules\Sync\Domain\ValueObjects\SyncAction;

/**
 * Trait para modelos que participan en sincronización offline-first.
 * 
 * Agrega tres columnas clave:
 * - sync_status: Estado actual (pending, synced, conflict, failed)
 * - version: Número de versión para detección de conflictos
 * - last_synced_at: Timestamp de última sincronización exitosa
 * 
 * También registra automáticamente cambios en la cola sync_queue
 * cuando el modelo es modificado (si la app está offline).
 */
trait Syncable
{
    /**
     * Boot del trait: registra hooks para tracking de cambios.
     */
    protected static function bootSyncable(): void
    {
        // Al crear: marcar como pending
        static::creating(function ($model) {
            if (empty($model->sync_status)) {
                $model->sync_status = SyncStatus::PENDING;
            }
            if (is_null($model->version)) {
                $model->version = 1;
            }
        });

        // Al actualizar: incrementar versión y marcar pending (si no es sync automático)
        static::updating(function ($model) {
            // Solo auto-marcar pending si estamos en modo offline o si no se está actualizando desde sync
            if (!app()->bound('sync.is_syncing') || !app('sync.is_syncing')) {
                // Solo marcar pending si cambió algún campo relevante
                if ($model->hasRelevantChanges()) {
                    $model->sync_status = SyncStatus::PENDING;
                    $model->version = ($model->version ?? 0) + 1;
                }
            }
        });

        // Después de crear/actualizar/eliminar: registrar en sync_queue
        static::created(function ($model) {
            static::logToSyncQueue($model, SyncAction::CREATE);
        });

        static::updated(function ($model) {
            if ($model->hasRelevantChanges()) {
                static::logToSyncQueue($model, SyncAction::UPDATE);
            }
        });

        static::deleted(function ($model) {
            static::logToSyncQueue($model, SyncAction::DELETE);
        });
    }

    /**
     * Determina si hay cambios relevantes (excluye timestamps y sync metadata).
     */
    protected function hasRelevantChanges(): bool
    {
        $ignoredFields = [
            'created_at', 'updated_at', 'deleted_at',
            'sync_status', 'version', 'last_synced_at',
        ];

        $changedFields = array_keys($this->getDirty());
        $relevantChanges = array_diff($changedFields, $ignoredFields);

        return !empty($relevantChanges);
    }

    /**
     * Registra el cambio en sync_queue para posterior sincronización.
     */
    protected static function logToSyncQueue($model, SyncAction $action): void
    {
        // Solo si la tabla sync_queue existe y no estamos sincronizando
        if (!\Illuminate\Support\Facades\Schema::hasTable('sync_queue')) {
            return;
        }

        if (app()->bound('sync.is_syncing') && app('sync.is_syncing')) {
            return;
        }

        try {
            \Illuminate\Support\Facades\DB::table('sync_queue')->insert([
                'uuid' => \Illuminate\Support\Str::uuid(),
                'company_id' => $model->company_id ?? null,
                'branch_id' => $model->branch_id ?? null,
                'entity_type' => get_class($model),
                'entity_id' => $model->id,
                'entity_uuid' => $model->uuid ?? null,
                'action' => $action->value,
                'payload' => json_encode($action === SyncAction::DELETE ? ['id' => $model->id] : $model->toArray()),
                'version' => $model->version ?? 1,
                'attempts' => 0,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Si falla el logging, no interrumpir la operación principal
            \Illuminate\Support\Facades\Log::warning('Sync queue logging failed', [
                'entity' => get_class($model),
                'id' => $model->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Marca el registro como sincronizado exitosamente.
     */
    public function markAsSynced(): bool
    {
        return $this->updateQuietly([
            'sync_status' => SyncStatus::SYNCED,
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Marca el registro con conflicto detectado.
     */
    public function markAsConflict(): bool
    {
        return $this->updateQuietly([
            'sync_status' => SyncStatus::CONFLICT,
        ]);
    }

    /**
     * Marca el registro como fallido (reintentar).
     */
    public function markAsFailed(): bool
    {
        return $this->updateQuietly([
            'sync_status' => SyncStatus::FAILED,
        ]);
    }

    /**
     * Verifica si el registro necesita sincronización.
     */
    public function needsSync(): bool
    {
        $status = $this->sync_status instanceof SyncStatus 
            ? $this->sync_status 
            : SyncStatus::tryFrom($this->sync_status);
        
        return $status && $status->needsSync();
    }

    /**
     * Verifica si el registro está sincronizado.
     */
    public function isSynced(): bool
    {
        $status = $this->sync_status instanceof SyncStatus 
            ? $this->sync_status 
            : SyncStatus::tryFrom($this->sync_status);
        
        return $status === SyncStatus::SYNCED;
    }

    public function scopePending($query)
    {
        return $query->where('sync_status', SyncStatus::PENDING);
    }

    public function scopeSynced($query)
    {
        return $query->where('sync_status', SyncStatus::SYNCED);
    }

    public function scopeWithConflicts($query)
    {
        return $query->where('sync_status', SyncStatus::CONFLICT);
    }
}
