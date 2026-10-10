<?php

namespace Modules\Sync\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Sync\Domain\Entities\SyncLog;
use Modules\Sync\Domain\Entities\SyncQueue;
use Modules\Sync\Domain\Exceptions\SyncException;
use Modules\Sync\Domain\Services\ServerDataProvider;
use Throwable;

/**
 * Servicio principal de sincronización.
 * 
 * O-02 FIX: Se ignora cualquier company_id o branch_id presente en el payload
 * del cliente, forzando el uso de los valores del SyncQueue (que ya fueron validados
 * contra el contexto del usuario autenticado).
 */
class SyncService
{
    public function pushChanges(int $branchId, int $limit = 100): array
    {
        $sessionId = (string) Str::uuid();
        $startTime = microtime(true);

        $results = [
            'session_id' => $sessionId,
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'conflicts' => 0,
            'errors' => [],
        ];

        $pendingChanges = SyncQueue::pending()
            ->forBranch($branchId)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        foreach ($pendingChanges as $queueItem) {
            try {
                $this->processQueueItem($queueItem, $sessionId);
                $results['processed']++;
                $results['success']++;
            } catch (SyncException $e) {
                $results['processed']++;
                if (str_contains($e->getMessage(), 'conflict')) {
                    $results['conflicts']++;
                } else {
                    $results['failed']++;
                }
                $results['errors'][] = [
                    'queue_id' => $queueItem->id,
                    'entity_type' => $queueItem->entity_type,
                    'entity_id' => $queueItem->entity_id,
                    'error' => $e->getMessage(),
                ];
            } catch (Throwable $e) {
                $results['processed']++;
                $results['failed']++;
                $results['errors'][] = [
                    'queue_id' => $queueItem->id,
                    'entity_type' => $queueItem->entity_type,
                    'entity_id' => $queueItem->entity_id,
                    'error' => $e->getMessage(),
                ];
                Log::error('SyncService: Unexpected error', [
                    'queue_id' => $queueItem->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $results['duration_ms'] = (int) ((microtime(true) - $startTime) * 1000);
        return $results;
    }

    protected function processQueueItem(SyncQueue $queueItem, string $sessionId): void
    {
        $startTime = microtime(true);

        $queueItem->status = 'processing';
        $queueItem->last_attempt_at = now();
        $queueItem->save();

        $exception = null;
        $result = 'success';
        $errorMessage = null;

        try {
            DB::transaction(function () use ($queueItem) {
                match ($queueItem->action->value) {
                    'create' => $this->processCreate($queueItem),
                    'update' => $this->processUpdate($queueItem),
                    'delete' => $this->processDelete($queueItem),
                    default => throw new SyncException(
                        "Unknown action: {$queueItem->action->value}",
                        $queueItem->entity_type,
                        $queueItem->entity_id
                    ),
                };

                $this->markEntityAsSynced($queueItem);
            });

            $queueItem->delete();
        } catch (SyncException $e) {
            $exception = $e;
            $result = 'error';
            $errorMessage = $e->getMessage();
        } catch (\Throwable $e) {
            $exception = $e;
            $result = 'error';
            $errorMessage = $e->getMessage();
        }

        if ($exception !== null) {
            $queueItem->status = 'failed';
            $queueItem->error_message = $errorMessage;
            $queueItem->attempts++;
            $queueItem->next_attempt_at = now()->addMinutes($queueItem->attempts * 5);
            $queueItem->save();
        }

        $this->logSync(
            sessionId: $sessionId,
            queueItem: $queueItem,
            result: $result,
            errorMessage: $errorMessage,
            durationMs: (int) ((microtime(true) - $startTime) * 1000)
        );

        if ($exception !== null) {
            if ($exception instanceof SyncException) {
                throw $exception;
            }
            throw new SyncException(
                $exception->getMessage(),
                $queueItem->entity_type,
                $queueItem->entity_id,
                0,
                $exception
            );
        }
    }

    /**
     * O-02 FIX: Procesa una creación forzando company_id y branch_id del contexto,
     * ignorando cualquier intento de escalada de privilegios en el payload.
     */
    protected function processCreate(SyncQueue $queueItem): void
    {
        $entity = $queueItem->getEntity();
        if (!$entity) {
            throw new SyncException(
                "Entity not found for create action",
                $queueItem->entity_type,
                $queueItem->entity_id
            );
        }

        if (method_exists($entity, 'validateForSync')) {
            $entity->validateForSync();
        }

        $entity->sync_status = 'synced';
        $entity->last_synced_at = now();
        $entity->saveQuietly();
    }

    /**
     * O-02 FIX: Procesa una actualización ignorando company_id y branch_id del payload.
     */
    protected function processUpdate(SyncQueue $queueItem): void
    {
        $entity = $queueItem->getEntity();
        if (!$entity) {
            throw new SyncException(
                "Entity not found for update action",
                $queueItem->entity_type,
                $queueItem->entity_id
            );
        }

        if (isset($queueItem->payload['version']) && (int)($entity->version ?? 1) !== (int)$queueItem->version) {
            throw new SyncException(
                "Version conflict detected: local={$queueItem->version}, current={$entity->version}",
                $queueItem->entity_type,
                $queueItem->entity_id
            );
        }

        $payload = $queueItem->payload;
        $fillable = $entity->getFillable();
        $data = array_intersect_key($payload, array_flip($fillable));
        
        // O-02 FIX: Eliminar campos de seguridad del payload para prevenir escalada
        unset($data['id'], $data['uuid'], $data['company_id'], $data['branch_id'], $data['version']);
        
        if (!empty($data)) {
            $entity->fill($data);
        }
        
        $entity->sync_status = 'synced';
        $entity->last_synced_at = now();
        $entity->saveQuietly();
    }

    protected function processDelete(SyncQueue $queueItem): void
    {
        $entity = $queueItem->getEntity();
        if ($entity) {
            $entity->delete();
        }
    }

    protected function markEntityAsSynced(SyncQueue $queueItem): void
    {
        $entity = $queueItem->getEntity();
        if (!$entity) {
            return;
        }

        if (method_exists($entity, 'markAsSynced')) {
            $entity->markAsSynced();
        } else {
            $entity->updateQuietly([
                'sync_status' => 'synced',
                'last_synced_at' => now(),
            ]);
        }
    }

    protected function logSync(
        string $sessionId,
        SyncQueue $queueItem,
        string $result,
        ?string $errorMessage = null,
        ?array $conflictData = null,
        int $durationMs = 0
    ): void {
        try {
            SyncLog::create([
                'company_id' => $queueItem->company_id,
                'branch_id' => $queueItem->branch_id,
                'sync_session_id' => $sessionId,
                'direction' => 'push',
                'entity_type' => $queueItem->entity_type,
                'entity_id' => $queueItem->entity_id,
                'entity_uuid' => $queueItem->entity_uuid,
                'action' => $queueItem->action->value,
                'result' => $result,
                'conflict_data' => $conflictData,
                'error_message' => $errorMessage,
                'duration_ms' => $durationMs,
                'synced_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('SyncService: Failed to write sync log', [
                'error' => $e->getMessage(),
                'queue_id' => $queueItem->id,
            ]);
        }
    }

    public function pullChanges(
        int $branchId,
        \Modules\Sync\Domain\Enums\ResolutionStrategy $conflictStrategy = \Modules\Sync\Domain\Enums\ResolutionStrategy::SERVER_WINS
    ): array {
        $sessionId = (string) Str::uuid();
        $startTime = microtime(true);

        $results = [
            'session_id' => $sessionId,
            'direction' => 'pull',
            'processed' => 0,
            'success' => 0,
            'conflicts' => 0,
            'errors' => [],
        ];

        $serverProvider = new ServerDataProvider();
        $conflictResolver = new ConflictResolver();

        $lastSync = $serverProvider->getLastSyncTimestamp($branchId);
        $serverChanges = $serverProvider->getChangesSince($branchId, $lastSync);

        foreach ($serverChanges as $change) {
            try {
                $this->applyServerChange($change, $sessionId, $conflictResolver, $conflictStrategy);
                $results['processed']++;
                $results['success']++;
            } catch (\Throwable $e) {
                $results['processed']++;
                $results['errors'][] = [
                    'entity_type' => $change['entity_type'] ?? null,
                    'entity_id' => $change['entity_id'] ?? null,
                    'error' => $e->getMessage(),
                ];
                Log::error('SyncService: Pull failed', [
                    'change' => $change,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($results['success'] > 0) {
            $serverProvider->acknowledgeChanges(
                $sessionId,
                collect($serverChanges)->pluck('entity_id')->toArray()
            );
        }

        $results['duration_ms'] = (int) ((microtime(true) - $startTime) * 1000);
        return $results;
    }

    protected function applyServerChange(
        array $change,
        string $sessionId,
        ConflictResolver $conflictResolver,
        \Modules\Sync\Domain\Enums\ResolutionStrategy $strategy
    ): void {
        $entityType = $change['entity_type'];
        $entityId = $change['entity_id'];

        if (!class_exists($entityType)) {
            throw new SyncException("Unknown entity type: {$entityType}");
        }

        $entity = $entityType::find($entityId);
        if (!$entity) {
            throw new SyncException("Entity not found: {$entityType}::{$entityId}");
        }

        if (isset($change['data']['company_id']) && property_exists($entity, 'company_id')) {
            if ($entity->company_id !== $change['data']['company_id']) {
                throw new SyncException("Entity {$entityType}::{$entityId} does not belong to company {$change['data']['company_id']}");
            }
        }
        
        if (isset($change['data']['branch_id']) && property_exists($entity, 'branch_id')) {
            if ($entity->branch_id !== $change['data']['branch_id']) {
                throw new SyncException("Entity {$entityType}::{$entityId} does not belong to branch {$change['data']['branch_id']}");
            }
        }

        $hasPendingChanges = $entity->sync_status === 'pending' || 
                             ($entity->sync_status instanceof \Modules\Sync\Domain\ValueObjects\SyncStatus && 
                              $entity->sync_status->value === 'pending');

        if ($hasPendingChanges) {
            $tempQueueItem = SyncQueue::create([
                'company_id' => $entity->company_id ?? null,
                'branch_id' => $entity->branch_id ?? null,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'entity_uuid' => $entity->uuid ?? null,
                'action' => 'update',
                'payload' => $entity->getDirty() ?: [],
                'version' => $entity->version ?? 1,
                'status' => 'pending',
            ]);

            $resolution = $conflictResolver->resolve($tempQueueItem, $change['data'], $strategy);
            if (!$resolution['resolved']) {
                throw new SyncException("Conflict not resolved for {$entityType}::{$entityId}", $entityType, $entityId);
            }
            $tempQueueItem->delete();
        } else {
            app()->instance('sync.is_syncing', true);
            try {
                $entity->fill($change['data']);
                $entity->sync_status = 'synced';
                $entity->version = $change['version'] ?? ($entity->version + 1);
                $entity->last_synced_at = now();
                $entity->save();
            } finally {
                app()->instance('sync.is_syncing', false);
            }
        }

        try {
            SyncLog::create([
                'uuid' => Str::uuid(),
                'company_id' => $entity->company_id ?? null,
                'branch_id' => $entity->branch_id ?? null,
                'sync_session_id' => $sessionId,
                'direction' => 'pull',
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'entity_uuid' => $entity->uuid ?? null,
                'action' => $change['action'] ?? 'update',
                'result' => 'success',
                'synced_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SyncService: Failed to log pull', ['error' => $e->getMessage()]);
        }
    }

    public function getSyncStats(int $branchId): array
    {
        return [
            'pending' => SyncQueue::pending()->forBranch($branchId)->count(),
            'processing' => SyncQueue::where('branch_id', $branchId)
                ->where('status', 'processing')
                ->count(),
            'failed' => SyncQueue::failed()->forBranch($branchId)->count(),
            'last_push' => SyncLog::pushes()
                ->where('branch_id', $branchId)
                ->latest('synced_at')
                ->first(),
        ];
    }
}
