<?php

namespace Modules\Sync\Domain\Services;

use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Entities\Order;
use Modules\Sync\Domain\Entities\SyncLog;
use Modules\Sync\Domain\Entities\SyncQueue;
use Modules\Sync\Domain\Enums\ResolutionStrategy;

/**
 * Resuelve conflictos de sincronización cuando el servidor
 * tiene una versión diferente a la del cliente.
 *
 * Estrategias disponibles:
 * - SERVER_WINS: El servidor gana, el cliente pierde sus cambios
 * - CLIENT_WINS: El cliente gana, sobrescribe el servidor
 * - MERGE: Intenta fusionar cambios (solo para campos no conflictivos)
 * - MANUAL: Marca para revisión manual (requiere intervención humana)
 */
class ConflictResolver
{
    /**
     * Resuelve un conflicto detectado durante la sincronización.
     *
     * @param SyncQueue $queueItem El item en cola con conflicto
     * @param array $serverData Datos actuales del servidor
     * @param ResolutionStrategy $strategy Estrategia de resolución
     * @return array Resultado de la resolución
     */
    public function resolve(
        SyncQueue $queueItem,
        array $serverData,
        ResolutionStrategy $strategy = ResolutionStrategy::SERVER_WINS
    ): array {
        $result = [
            'resolved' => false,
            'strategy' => $strategy->value,
            'action_taken' => null,
            'conflict_details' => [],
        ];

        try {
            $entity = $queueItem->getEntity();
            
            if (!$entity) {
                $result['action_taken'] = 'skip_entity_not_found';
                return $result;
            }

            // Detectar campos en conflicto
            $conflicts = $this->detectConflicts($queueItem, $serverData);
            $result['conflict_details'] = $conflicts;

            // Aplicar estrategia
            switch ($strategy) {
                case ResolutionStrategy::SERVER_WINS:
                    $this->applyServerWins($entity, $serverData);
                    $result['action_taken'] = 'server_data_applied';
                    $result['resolved'] = true;
                    break;

                case ResolutionStrategy::CLIENT_WINS:
                    $this->applyClientWins($queueItem, $entity);
                    $result['action_taken'] = 'client_data_preserved';
                    $result['resolved'] = true;
                    break;

                case ResolutionStrategy::MERGE:
                    $merged = $this->applyMerge($queueItem, $serverData, $conflicts);
                    $result['action_taken'] = $merged ? 'fields_merged' : 'merge_failed_conflicts';
                    $result['resolved'] = $merged;
                    break;

                case ResolutionStrategy::MANUAL:
                    $this->markForManualReview($queueItem, $conflicts);
                    $result['action_taken'] = 'marked_for_manual_review';
                    $result['resolved'] = false;
                    break;
            }

            // Registrar en sync_log
            $this->logConflictResolution($queueItem, $result);

        } catch (\Throwable $e) {
            Log::error('ConflictResolver failed', [
                'queue_id' => $queueItem->id,
                'error' => $e->getMessage(),
            ]);
            
            $result['action_taken'] = 'resolution_failed';
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Detecta qué campos están en conflicto.
     */
    protected function detectConflicts(SyncQueue $queueItem, array $serverData): array
    {
        $conflicts = [];
        $clientData = $queueItem->payload ?? [];

        // Campos que pueden fusionarse automáticamente
        $mergeableFields = ['notes', 'updated_at'];
        
        // Campos que generan conflicto si ambos cambian
        $conflictFields = [
            'status', 'subtotal', 'tax_amount', 'discount_amount', 
            'total', 'waiter_id', 'table_id', 'assigned_cook_id'
        ];

        foreach ($conflictFields as $field) {
            $clientValue = $clientData[$field] ?? null;
            $serverValue = $serverData[$field] ?? null;

            // Si ambos tienen valores diferentes, hay conflicto
            if ($clientValue !== null && $serverValue !== null && $clientValue !== $serverValue) {
                $conflicts[$field] = [
                    'client' => $clientValue,
                    'server' => $serverValue,
                    'mergeable' => false,
                ];
            }
        }

        return $conflicts;
    }

    /**
     * SERVER_WINS: Aplica los datos del servidor al cliente.
     */
    protected function applyServerWins($entity, array $serverData): void
    {
        // Marcar que estamos sincronizando (evita que Syncable incremente versión)
        app()->instance('sync.is_syncing', true);

        try {
            // Actualizar la entidad local con datos del servidor
            $entity->fill($serverData);
            $entity->sync_status = 'synced';
            $entity->version = $serverData['version'] ?? $entity->version;
            $entity->last_synced_at = now();
            $entity->save();
        } finally {
            app()->instance('sync.is_syncing', false);
        }
    }

    /**
     * CLIENT_WINS: Preserva los datos del cliente (próximo push sobrescribirá servidor).
     */
    protected function applyClientWins(SyncQueue $queueItem, $entity): void
    {
        // Marcar como pending para reintentar con datos del cliente
        $queueItem->status = 'pending';
        $queueItem->attempts = 0;
        $queueItem->save();

        // Mantener sync_status en pending para forzar nuevo push
        $entity->sync_status = 'pending';
        $entity->save();
    }

    /**
     * MERGE: Intenta fusionar campos no conflictivos.
     */
    protected function applyMerge(SyncQueue $queueItem, array $serverData, array $conflicts): bool
    {
        // Si hay conflictos en campos críticos, no se puede fusionar
        if (!empty($conflicts)) {
            return false;
        }

        $entity = $queueItem->getEntity();
        if (!$entity) {
            return false;
        }

        // Marcar que estamos sincronizando
        app()->instance('sync.is_syncing', true);

        try {
            // Fusionar solo campos mergeables
            $clientData = $queueItem->payload ?? [];
            $mergeableFields = ['notes'];
            
            foreach ($mergeableFields as $field) {
                if (isset($clientData[$field]) && isset($serverData[$field])) {
                    // Concatenar notas con separador
                    $merged = $serverData[$field] . "\n--- Cliente: " . $clientData[$field];
                    $entity->{$field} = $merged;
                }
            }

            $entity->sync_status = 'synced';
            $entity->version = $serverData['version'] ?? $entity->version;
            $entity->last_synced_at = now();
            $entity->save();
        } finally {
            app()->instance('sync.is_syncing', false);
        }

        return true;
    }

    /**
     * MANUAL: Marca para revisión humana.
     */
    protected function markForManualReview(SyncQueue $queueItem, array $conflicts): void
    {
        $queueItem->status = 'conflict';
        $queueItem->error_message = 'Conflicto detectado, requiere revisión manual';
        $queueItem->conflict_data = $conflicts;
        $queueItem->save();
    }

    /**
     * Registra la resolución en sync_log.
     */
    protected function logConflictResolution(SyncQueue $queueItem, array $result): void
    {
        try {
            SyncLog::create([
                'uuid' => \Illuminate\Support\Str::uuid(),
                'company_id' => $queueItem->company_id,
                'branch_id' => $queueItem->branch_id,
                'sync_session_id' => (string) \Illuminate\Support\Str::uuid(),
                'direction' => 'push',
                'entity_type' => $queueItem->entity_type,
                'entity_id' => $queueItem->entity_id,
                'entity_uuid' => $queueItem->entity_uuid,
                'action' => $queueItem->action->value,
                'result' => $result['resolved'] ? 'success' : 'conflict',
                'conflict_data' => [
                    'strategy' => $result['strategy'],
                    'action_taken' => $result['action_taken'],
                    'resolved' => $result['resolved'],
                    'conflicts' => $result['conflict_details'],
                ],
                'synced_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ConflictResolver: Failed to write log', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
