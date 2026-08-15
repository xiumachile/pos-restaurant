<?php

namespace Modules\Sync\Domain\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;

/**
 * Adaptador de sincronización entre Postgres (servidor) y SQLite (local).
 * 
 * Responsabilidades:
 * - Exportar entidades del servidor a la BD local
 * - Importar entidades de la BD local al servidor
 * - Manejar transformaciones de datos entre esquemas
 * - Preservar integridad referencial
 */
class SyncAdapter
{
    protected LocalDatabaseManager $localDb;
    protected EntityMapper $mapper;
    protected string $localConnection = 'sqlite_local';

    public function __construct(
        ?LocalDatabaseManager $localDb = null,
        ?EntityMapper $mapper = null
    ) {
        $this->localDb = $localDb ?? new LocalDatabaseManager();
        $this->mapper = $mapper ?? new EntityMapper();
    }

    /**
     * Exporta todas las órdenes de una sucursal a la BD local.
     * 
     * @param int $branchId ID de la sucursal
     * @return array Resumen de la exportación
     */
    public function exportOrdersToLocal(int $branchId): array
    {
        $startTime = microtime(true);
        
        // Asegurar que la BD local esté inicializada
        if (!$this->localDb->isAvailable()) {
            $this->localDb->initialize();
        }

        $results = [
            'exported_orders' => 0,
            'exported_items' => 0,
            'errors' => [],
        ];

        try {
            // Obtener órdenes de la sucursal
            $orders = Order::where('branch_id', $branchId)
                ->with('items')
                ->get();

            $localConnection = DB::connection($this->localConnection);

            foreach ($orders as $order) {
                try {
                    $this->exportSingleOrder($order, $localConnection);
                    $results['exported_orders']++;
                    $results['exported_items'] += $order->items->count();
                } catch (\Throwable $e) {
                    $results['errors'][] = [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Actualizar metadata de última exportación
            $this->updateMetadata($localConnection, 'last_export_at', now()->toIso8601String());
            $this->updateMetadata($localConnection, 'last_export_branch_id', (string) $branchId);

        } catch (\Throwable $e) {
            Log::error('SyncAdapter: Export failed', [
                'branch_id' => $branchId,
                'error' => $e->getMessage(),
            ]);
            $results['errors'][] = ['error' => $e->getMessage()];
        }

        $results['duration_ms'] = (int) ((microtime(true) - $startTime) * 1000);

        return $results;
    }

    /**
     * Exporta una sola orden a la BD local.
     */
    protected function exportSingleOrder(Order $order, $localConnection): void
    {
        $localData = $this->mapper->orderToLocal($order);

        // Upsert: actualizar si existe, insertar si no
        $existing = $localConnection->table('local_orders')
            ->where('uuid', $order->uuid)
            ->first();

        if ($existing) {
            $localConnection->table('local_orders')
                ->where('uuid', $order->uuid)
                ->update($localData);
            $localOrderId = $existing->id;
        } else {
            $localOrderId = $localConnection->table('local_orders')
                ->insertGetId($localData);
        }

        // Exportar items
        foreach ($order->items as $item) {
            $this->exportSingleOrderItem($item, $localOrderId, $localConnection);
        }
    }

    /**
     * Exporta un solo item de orden a la BD local.
     */
    protected function exportSingleOrderItem(OrderItem $item, int $localOrderId, $localConnection): void
    {
        $localData = $this->mapper->orderItemToLocal($item, $localOrderId);

        $existing = $localConnection->table('local_order_items')
            ->where('uuid', $item->uuid)
            ->first();

        if ($existing) {
            $localConnection->table('local_order_items')
                ->where('uuid', $item->uuid)
                ->update($localData);
        } else {
            $localConnection->table('local_order_items')
                ->insert($localData);
        }
    }

    /**
     * Importa órdenes de la BD local al servidor.
     * 
     * @param int $branchId ID de la sucursal
     * @return array Resumen de la importación
     */
    public function importOrdersFromLocal(int $branchId): array
    {
        $startTime = microtime(true);

        $results = [
            'imported_orders' => 0,
            'skipped_orders' => 0,
            'errors' => [],
        ];

        try {
            $localConnection = DB::connection($this->localConnection);

            // Obtener órdenes locales pendientes de sincronización
            $localOrders = $localConnection->table('local_orders')
                ->where('branch_id', $branchId)
                ->where('sync_status', 'pending')
                ->get();

            foreach ($localOrders as $localOrder) {
                try {
                    $imported = $this->importSingleOrder((array) $localOrder, $branchId);
                    
                    if ($imported) {
                        $results['imported_orders']++;
                        
                        // Marcar como sincronizada en local
                        $localConnection->table('local_orders')
                            ->where('id', $localOrder->id)
                            ->update([
                                'sync_status' => 'synced',
                                'last_synced_at' => now()->toIso8601String(),
                            ]);
                    } else {
                        $results['skipped_orders']++;
                    }
                } catch (\Throwable $e) {
                    $results['errors'][] = [
                        'local_order_id' => $localOrder->id,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $this->updateMetadata($localConnection, 'last_import_at', now()->toIso8601String());

        } catch (\Throwable $e) {
            Log::error('SyncAdapter: Import failed', [
                'branch_id' => $branchId,
                'error' => $e->getMessage(),
            ]);
            $results['errors'][] = ['error' => $e->getMessage()];
        }

        $results['duration_ms'] = (int) ((microtime(true) - $startTime) * 1000);

        return $results;
    }

    /**
     * Importa una sola orden local al servidor.
     */
    protected function importSingleOrder(array $localData, int $branchId): bool
    {
        // Verificar si ya existe en el servidor (por uuid)
        $existingOrder = null;
        if (!empty($localData['uuid'])) {
            $existingOrder = Order::withoutGlobalScopes()
                ->where('uuid', $localData['uuid'])
                ->first();
        }

        if ($existingOrder) {
            // Ya existe: actualizar si la versión local es mayor
            if (($localData['version'] ?? 1) > ($existingOrder->version ?? 1)) {
                $serverData = $this->mapper->localToOrder($localData);
                unset($serverData['uuid']); // No actualizar uuid
                
                app()->instance('sync.is_syncing', true);
                try {
                    $existingOrder->update($serverData);
                } finally {
                    app()->instance('sync.is_syncing', false);
                }
                return true;
            }
            return false; // Versión del servidor es mayor o igual
        }

        // No existe: crear nueva orden en el servidor
        $serverData = $this->mapper->localToOrder($localData);
        $serverData['branch_id'] = $branchId;
        // Asegurar que company_id y branch_id estén presentes
        if (empty($serverData['company_id'])) {
            $serverData['company_id'] = DB::table('branches')
                ->where('id', $branchId)
                ->value('company_id');
        }
        // Asegurar que company_id y branch_id estén presentes
        if (empty($serverData['company_id'])) {
            $serverData['company_id'] = DB::table('branches')
                ->where('id', $branchId)
                ->value('company_id');
        }
        $serverData['company_id'] = DB::table('branches')
            ->where('id', $branchId)
            ->value('company_id');

        // Generar order_number si no existe
        if (empty($serverData['order_number'])) {
            $serverData['order_number'] = 'ORD-IMP-' . uniqid();
        }

        app()->instance('sync.is_syncing', true);
        try {
            $order = Order::forceCreate($serverData);
            
            // Importar items si existen
            $localConnection = DB::connection($this->localConnection);
            $localItems = $localConnection->table('local_order_items')
                ->where('local_order_id', $localData['id'])
                ->get();

            foreach ($localItems as $localItem) {
                $this->importSingleOrderItem((array) $localItem, $order);
            }
        } finally {
            app()->instance('sync.is_syncing', false);
        }

        return true;
    }

    /**
     * Importa un solo item local al servidor.
     */
    protected function importSingleOrderItem(array $localData, Order $order): void
    {
        $itemData = $this->mapper->localToOrderItem($localData);
        $itemData['order_id'] = $order->id;
        $itemData['company_id'] = $order->company_id;

        OrderItem::forceCreate($itemData);
    }

    /**
     * Actualiza metadata en la BD local.
     */
    protected function updateMetadata($localConnection, string $key, string $value): void
    {
        try {
            $localConnection->table('local_sync_metadata')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        } catch (\Throwable $e) {
            Log::warning('SyncAdapter: Failed to update metadata', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtiene metadata de la BD local.
     */
    public function getMetadata(string $key): ?string
    {
        try {
            $localConnection = DB::connection($this->localConnection);
            return $localConnection->table('local_sync_metadata')
                ->where('key', $key)
                ->value('value');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
