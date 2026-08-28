<?php

namespace Modules\Sync\Domain\Services;

use Illuminate\Support\Collection;
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
 * Coordina el envío (push) y recepción (pull) de cambios
 * entre el cliente offline y el servidor.
 * 
 * Flujo de push:
 * 1. Obtiene cambios pendientes de sync_queue
 * 2. Para cada cambio, marca como processing
 * 3. "Envía" al servidor (aplica cambios)
 * 4. Marca la entidad original como synced
 * 5. Registra en sync_log
 * 6. Elimina de sync_queue
 */
class SyncService
{
    /**
     * Envía todos los cambios pendientes de una sucursal al servidor.
     * 
     * @param int $branchId ID de la sucursal
     * @param int $limit Máximo de cambios a procesar
     * @return array Resumen de la operación
     */
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

        // Obtener cambios pendientes para esta sucursal
        $pendingChanges = SyncQueue::pending()
            ->forBranch($branchId)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        foreach ($pendingChanges as $queueItem) {
            $itemStart = microtime(true);
            
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

    /**
     * Procesa un item individual de la cola de sincronización.
     */
    protected function processQueueItem(SyncQueue $queueItem, string $sessionId): void
    {
        $startTime = microtime(true);

        // Marcar como procesando (FUERA de la transacción principal)
        $queueItem->status = 'processing';
        $queueItem->last_attempt_at = now();
        $queueItem->save();

        $exception = null;
        $result = 'success';
        $errorMessage = null;

        try {
            DB::transaction(function () use ($queueItem) {
                // Procesar según la acción
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

                // Marcar la entidad original como synced
                $this->markEntityAsSynced($queueItem);
            });

            // Si llegamos aquí, fue exitoso: eliminar de la cola
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

        // FUERA de la transacción: actualizar estado del queue item
        if ($exception !== null) {
            $queueItem->status = 'failed';
            $queueItem->error_message = $errorMessage;
            $queueItem->attempts++;
            $queueItem->next_attempt_at = now()->addMinutes($queueItem->attempts * 5);
            $queueItem->save();
        }

        // Registrar log siempre (fuera de la transacción de procesamiento)
        $this->logSync(
            sessionId: $sessionId,
            queueItem: $queueItem,
            result: $result,
            errorMessage: $errorMessage,
            durationMs: (int) ((microtime(true) - $startTime) * 1000)
        );

        // Re-lanzar excepción para que el caller la capture
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
     * Procesa una creación.
     * En un escenario real, aquí se enviaría al servidor externo.
     * Por ahora, simulamos éxito (la entidad ya fue creada localmente).
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

        // En producción: enviar a API externa
        // Por ahora: validar que existe y continuar
    }

    /**
     * Procesa una actualización.
     * Detecta conflictos de versión si aplica.
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

        // Verificar conflicto de versión
        if (isset($queueItem->payload['server_version'])) {
            $serverVersion = (int) $queueItem->payload['server_version'];
            $currentVersion = (int) ($entity->version ?? 1);
            
            if ($currentVersion !== $queueItem->version) {
                throw new SyncException(
                    "Version conflict detected: local={$queueItem->version}, current={$currentVersion}",
                    $queueItem->entity_type,
                    $queueItem->entity_id
                );
            }
        }

        // En producción: enviar a API externa
    }

    /**
     * Procesa una eliminación.
     */
    protected function processDelete(SyncQueue $queueItem): void
    {
        // En producción: enviar delete a API externa
        // La entidad ya está eliminada localmente (soft delete o hard)
    }

    /**
     * Marca la entidad original como sincronizada.
     */
    protected function markEntityAsSynced(SyncQueue $queueItem): void
    {
        $entity = $queueItem->getEntity();
        
        if (!$entity) {
            return;
        }

        if (method_exists($entity, 'markAsSynced')) {
            $entity->markAsSynced();
        } else {
            // Fallback: update silencioso directo
            $entity->updateQuietly([
                'sync_status' => 'synced',
                'last_synced_at' => now(),
            ]);
        }
    }

    /**
     * Registra la operación en sync_log.
     */
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
            // No fallar el sync si falla el logging
            Log::warning('SyncService: Failed to write sync log', [
                'error' => $e->getMessage(),
                'queue_id' => $queueItem->id,
            ]);
        }
    }

    /**
     * Obtiene estadísticas de sync para una sucursal.
     */

    /**
     * Descarga cambios del servidor y los aplica al cliente.
     * 
     * @param int $branchId ID de la sucursal
     * @param ResolutionStrategy $conflictStrategy Estrategia para resolver conflictos
     * @return array Resumen de la operación
     */
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

        // Obtener timestamp de última sincronización
        $lastSync = $serverProvider->getLastSyncTimestamp($branchId);

        // Obtener cambios del servidor
        $serverChanges = $serverProvider->getChangesSince($branchId, $lastSync);

        foreach ($serverChanges as $change) {
            $itemStart = microtime(true);

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

        // Confirmar cambios en el servidor
        if ($results['success'] > 0) {
            $serverProvider->acknowledgeChanges(
                $sessionId,
                collect($serverChanges)->pluck('entity_id')->toArray()
            );
        }

        $results['duration_ms'] = (int) ((microtime(true) - $startTime) * 1000);

        return $results;
    }

    /**
     * Aplica un cambio del servidor al cliente.
     */
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

        // S1.3: Validación defensiva - verificar que la entidad pertenezca al tenant
        // El servidor ya filtra por tenant, pero validamos como defensa en profundidad
        if (isset($change['data']['company_id']) && property_exists($entity, 'company_id')) {
            if ($entity->company_id !== $change['data']['company_id']) {
                throw new SyncException(
                    "Entity {$entityType}::{$entityId} does not belong to company {$change['data']['company_id']}"
                );
            }
        }
        
        if (isset($change['data']['branch_id']) && property_exists($entity, 'branch_id')) {
            if ($entity->branch_id !== $change['data']['branch_id']) {
                throw new SyncException(
                    "Entity {$entityType}::{$entityId} does not belong to branch {$change['data']['branch_id']}"
                );
            }
        }

        // Verificar si el cliente tiene cambios pendientes (conflicto potencial)
        $hasPendingChanges = $entity->sync_status === 'pending' || 
                             ($entity->sync_status instanceof \Modules\Sync\Domain\ValueObjects\SyncStatus && 
                              $entity->sync_status->value === 'pending');

        if ($hasPendingChanges) {
            // Conflicto: el cliente tiene cambios sin sincronizar
            // Crear un queue item temporal para resolver el conflicto
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
                throw new SyncException(
                    "Conflict not resolved for {$entityType}::{$entityId}",
                    $entityType,
                    $entityId
                );
            }

            // Limpiar el queue item temporal
            $tempQueueItem->delete();
        } else {
            // Sin conflicto: aplicar cambios directamente
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

        // Registrar en sync_log
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
