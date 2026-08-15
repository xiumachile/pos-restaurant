<?php

namespace Modules\Sync\Domain\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Proveedor de datos del servidor.
 * 
 * En producción, este servicio haría llamadas HTTP al servidor central.
 * Por ahora, simula el comportamiento del servidor para el motor offline.
 * 
 * Flujo real en producción:
 * 1. Cliente llama a GET /api/sync/changes?since={timestamp}
 * 2. Servidor retorna cambios desde esa fecha
 * 3. Cliente aplica cambios localmente
 * 4. Cliente confirma recepción con POST /api/sync/ack
 */
class ServerDataProvider
{
    /**
     * Obtiene cambios del servidor desde una fecha específica.
     * 
     * @param int $branchId ID de la sucursal
     * @param \DateTimeInterface|null $since Fecha desde la cual obtener cambios
     * @return Collection Colección de cambios del servidor
     */
    public function getChangesSince(int $branchId, ?\DateTimeInterface $since = null): Collection
    {
        // En producción: llamada HTTP al servidor
        // $response = Http::get("{$this->serverUrl}/api/sync/changes", [
        //     'branch_id' => $branchId,
        //     'since' => $since?->toIso8601String(),
        // ]);
        
        // Simulación: retornar cambios de entidades modificadas después de $since
        $query = \Modules\Orders\Domain\Entities\Order::query()
            ->where('branch_id', $branchId)
            ->where('sync_status', 'synced');
        
        if ($since) {
            $query->where('updated_at', '>', $since);
        }
        
        return $query->get()->map(function ($order) {
            return [
                'entity_type' => get_class($order),
                'entity_id' => $order->id,
                'entity_uuid' => $order->uuid,
                'action' => 'update',
                'version' => $order->version,
                'data' => $order->toArray(),
                'updated_at' => $order->updated_at?->toIso8601String(),
            ];
        });
    }

    /**
     * Confirma la recepción de cambios en el servidor.
     * 
     * @param string $sessionId ID de la sesión de sync
     * @param array $acknowledgedIds IDs de entidades confirmadas
     * @return bool
     */
    public function acknowledgeChanges(string $sessionId, array $acknowledgedIds): bool
    {
        // En producción: POST /api/sync/ack
        // Por ahora: solo log
        Log::info('ServerDataProvider: Changes acknowledged', [
            'session_id' => $sessionId,
            'count' => count($acknowledgedIds),
        ]);
        
        return true;
    }

    /**
     * Obtiene el timestamp de la última sincronización exitosa.
     */
    public function getLastSyncTimestamp(int $branchId): ?\DateTimeInterface
    {
        $lastLog = \Modules\Sync\Domain\Entities\SyncLog::where('branch_id', $branchId)
            ->where('direction', 'pull')
            ->where('result', 'success')
            ->latest('synced_at')
            ->first();
        
        return $lastLog?->synced_at;
    }
}
