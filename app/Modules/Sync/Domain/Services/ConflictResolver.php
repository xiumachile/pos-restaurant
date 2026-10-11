<?php

namespace Modules\Sync\Domain\Services;

use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Entities\Order;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Cashier\Domain\Entities\CashMovement;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Sync\Domain\Entities\SyncLog;
use Modules\Sync\Domain\Entities\SyncQueue;
use Modules\Sync\Domain\Enums\ResolutionStrategy;

/**
 * Resuelve conflictos de sincronización cuando el servidor
 * tiene una versión diferente a la del cliente.
 *
 * O-06 FIX: Políticas explícitas por tipo de entidad para prevenir
 * la pérdida silenciosa de operaciones financieras.
 */
class ConflictResolver
{
    /**
     * O-06 FIX: Mapa de políticas de conflicto por tipo de entidad.
     * Las entidades financieras NO permiten SERVER_WINS para evitar pérdida de datos.
     */
    protected const ENTITY_CONFLICT_POLICIES = [
        // Catálogo: El servidor es la autoridad
        \Modules\Catalog\Domain\Entities\Category::class => ResolutionStrategy::SERVER_WINS,
        \Modules\Catalog\Domain\Entities\Product::class => ResolutionStrategy::SERVER_WINS,
        \Modules\Tables\Domain\Entities\RestaurantTable::class => ResolutionStrategy::SERVER_WINS,
        
        // Pedidos: Fusión controlada o revisión manual si hay conflicto en montos/estado
        \Modules\Orders\Domain\Entities\Order::class => ResolutionStrategy::MERGE,
        
        // Entidades financieras: INMUTABLES. Deduplicación por clave o revisión manual.
        // NUNCA SERVER_WINS para evitar sobrescribir pagos o movimientos de caja locales.
        Payment::class => ResolutionStrategy::MANUAL,
        CashMovement::class => ResolutionStrategy::MANUAL,
        Bill::class => ResolutionStrategy::MANUAL,
    ];

    /**
     * Entidades que soportan deduplicación por idempotency_key.
     */
    protected const DEDUPLICABLE_ENTITIES = [
        Payment::class,
        \Modules\Orders\Domain\Entities\Order::class,
    ];

    /**
     * Resuelve un conflicto detectado durante la sincronización.
     *
     * @param SyncQueue $queueItem El item en cola con conflicto
     * @param array $serverData Datos actuales del servidor
     * @param ResolutionStrategy $strategy Estrategia de resolución solicitada
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
                $result['action_taken'] = 'skip_entity_not_found / 实体未找到';
                return $result;
            }

            // O-06 FIX: Forzar política segura según el tipo de entidad
            $safeStrategy = $this->getSafeStrategyForEntity($queueItem->entity_type, $strategy);
            $result['strategy'] = $safeStrategy->value;

            // O-06 FIX: Intentar deduplicación por idempotency_key antes de marcar conflicto
            if (in_array($queueItem->entity_type, self::DEDUPLICABLE_ENTITIES)) {
                $dedupResult = $this->attemptDeduplication($queueItem, $serverData);
                if ($dedupResult['resolved']) {
                    return array_merge($result, $dedupResult);
                }
            }

            // Detectar campos en conflicto
            $conflicts = $this->detectConflicts($queueItem, $serverData);
            $result['conflict_details'] = $conflicts;

            // Aplicar estrategia segura
            switch ($safeStrategy) {
                case ResolutionStrategy::SERVER_WINS:
                    $this->applyServerWins($entity, $serverData);
                    $result['action_taken'] = 'server_data_applied / 应用服务器数据';
                    $result['resolved'] = true;
                    break;

                case ResolutionStrategy::CLIENT_WINS:
                    $this->applyClientWins($queueItem, $entity);
                    $result['action_taken'] = 'client_data_preserved / 保留客户端数据';
                    $result['resolved'] = true;
                    break;

                case ResolutionStrategy::MERGE:
                    $merged = $this->applyMerge($queueItem, $serverData, $conflicts);
                    if ($merged) {
                        $result['action_taken'] = 'fields_merged / 字段已合并';
                        $result['resolved'] = true;
                    } else {
                        $this->markForManualReview($queueItem, $conflicts, ResolutionStrategy::MANUAL);
                        $result['action_taken'] = 'merge_failed_fallback_to_manual / 合并失败，转为人工审核';
                        $result['resolved'] = false;
                    }
                    $result['resolved'] = $merged;
                    break;

                case ResolutionStrategy::MANUAL:
                    $this->markForManualReview($queueItem, $conflicts, $safeStrategy);
                    $result['action_taken'] = 'marked_for_manual_review / 标记为人工审核';
                    $result['resolved'] = false;
                    break;
            }

            // Registrar en sync_log
            $this->logConflictResolution($queueItem, $result);

        } catch (\Throwable $e) {
            Log::error('ConflictResolver failed / 冲突解决失败', [
                'queue_id' => $queueItem->id,
                'error' => $e->getMessage(),
            ]);
            
            $result['action_taken'] = 'resolution_failed / 解决失败';
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * O-06 FIX: Obtiene la estrategia segura para una entidad, previniendo SERVER_WINS en datos financieros.
     */
    protected function getSafeStrategyForEntity(string $entityType, ResolutionStrategy $requestedStrategy): ResolutionStrategy
    {
        // O-06 FIX: Solo bloquear SERVER_WINS para entidades financieras.
        // Para otras entidades (Order, Category, etc.), respetar la estrategia solicitada.
        $financialEntities = [Payment::class, CashMovement::class, Bill::class];
        
        if (in_array($entityType, $financialEntities) && $requestedStrategy === ResolutionStrategy::SERVER_WINS) {
            Log::warning("ConflictResolver: Blocked SERVER_WINS for financial entity {$entityType} / 阻止对财务实体使用 SERVER_WINS", [
                'entity_type' => $entityType,
            ]);
            return ResolutionStrategy::MANUAL;
        }

        // Para entidades no financieras, respetar la estrategia solicitada
        return $requestedStrategy;
    }

    /**
     * O-06 FIX: Intenta resolver el conflicto mediante deduplicación por idempotency_key.
     * Si el servidor ya tiene un registro con la misma clave de idempotencia, 
     * consideramos el sync exitoso sin sobrescribir datos.
     */
    protected function attemptDeduplication(SyncQueue $queueItem, array $serverData): array
    {
        $clientPayload = $queueItem->payload ?? [];
        $clientIdempotency = $clientPayload['idempotency_key'] ?? null;
        $serverIdempotency = $serverData['idempotency_key'] ?? null;

        if ($clientIdempotency && $clientIdempotency === $serverIdempotency) {
            // Ya existe en el servidor con la misma clave. Marcar como synced localmente.
            $entity = $queueItem->getEntity();
            if ($entity) {
                app()->instance('sync.is_syncing', true);
                try {
                    $entity->sync_status = 'synced';
                    $entity->last_synced_at = now();
                    $entity->save();
                } finally {
                    app()->instance('sync.is_syncing', false);
                }
            }
            
            return [
                'resolved' => true,
                'action_taken' => 'deduplicated_by_idempotency_key / 通过幂等键去重',
                'conflict_details' => [],
            ];
        }

        return ['resolved' => false];
    }

    /**
     * Detecta qué campos están en conflicto.
     */
    protected function detectConflicts(SyncQueue $queueItem, array $serverData): array
    {
        $conflicts = [];
        $clientData = $queueItem->payload ?? [];

        $mergeableFields = ['notes', 'updated_at'];
        $conflictFields = [
            'status', 'subtotal', 'tax_amount', 'discount_amount', 
            'total', 'amount', 'tip_amount', 'waiter_id', 'table_id', 'assigned_cook_id'
        ];

        foreach ($conflictFields as $field) {
            $clientValue = $clientData[$field] ?? null;
            $serverValue = $serverData[$field] ?? null;

            if ($clientValue !== null && $serverValue !== null && $clientValue !== $serverValue) {
                $conflicts[$field] = [
                    'client' => $clientValue,
                    'server' => $serverValue,
                    'mergeable' => in_array($field, $mergeableFields),
                ];
            }
        }

        return $conflicts;
    }

    protected function applyServerWins($entity, array $serverData): void
    {
        app()->instance('sync.is_syncing', true);
        try {
            $entity->fill($serverData);
            $entity->sync_status = 'synced';
            $entity->version = $serverData['version'] ?? $entity->version;
            $entity->last_synced_at = now();
            $entity->save();
        } finally {
            app()->instance('sync.is_syncing', false);
        }
    }

    protected function applyClientWins(SyncQueue $queueItem, $entity): void
    {
        $queueItem->status = 'pending';
        $queueItem->attempts = 0;
        $queueItem->save();

        $entity->sync_status = 'pending';
        $entity->save();
    }

    protected function applyMerge(SyncQueue $queueItem, array $serverData, array $conflicts): bool
    {
        // Si hay conflictos en campos críticos (no mergeables), no se puede fusionar
        $hasCriticalConflicts = false;
        foreach ($conflicts as $field => $details) {
            if (!$details['mergeable']) {
                $hasCriticalConflicts = true;
                break;
            }
        }

        if ($hasCriticalConflicts) {
            return false;
        }

        $entity = $queueItem->getEntity();
        if (!$entity) {
            return false;
        }

        app()->instance('sync.is_syncing', true);
        try {
            $clientData = $queueItem->payload ?? [];
            $mergeableFields = ['notes'];
            
            foreach ($mergeableFields as $field) {
                if (isset($clientData[$field]) && isset($serverData[$field]) && $clientData[$field] !== $serverData[$field]) {
                    $entity->{$field} = $serverData[$field] . "\n--- Cliente / Client: " . $clientData[$field];
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

    protected function markForManualReview(SyncQueue $queueItem, array $conflicts, ResolutionStrategy $forcedStrategy): void
    {
        $queueItem->status = 'conflict';
        $queueItem->error_message = "Conflicto detectado (Política: {$forcedStrategy->value}), requiere revisión manual / 检测到冲突 (策略: {$forcedStrategy->value})，需要人工审核";
        $queueItem->conflict_data = array_merge($conflicts, ['forced_strategy' => $forcedStrategy->value]);
        $queueItem->save();
    }

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
            Log::warning('ConflictResolver: Failed to write log / 记录日志失败', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
