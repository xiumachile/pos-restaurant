<?php

namespace Modules\Inventory\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Inventory\Domain\Entities\InventoryItem;
use Modules\Inventory\Domain\Services\InventoryService;
use Modules\Orders\Domain\Events\OrderCancelled;

/**
 * Listener que devuelve stock cuando un pedido es cancelado.
 * Se registra en OrderEventServiceProvider.
 */
class ReturnStockOnOrderCancel
{
    public function __construct(
        private InventoryService $inventoryService
    ) {}

    public function handle(OrderCancelled $event): void
    {
        $order = $event->order;
        
        // NOTA: No verificamos $order->status porque cuando el evento se dispara,
        // el estado ya cambió a CANCELLED. La maquina de estados ya validó que
        // solo se pueden cancelar pedidos en estados activos (draft, confirmed, preparing).
        // Por lo tanto, siempre devolvemos stock cuando se cancela un pedido.
        
        foreach ($order->items as $item) {
            $inventoryItem = InventoryItem::where('company_id', $order->company_id)
                ->where('sku', $item->name_snapshot)
                ->first();
            
            if (!$inventoryItem) {
                // Item no tiene control de inventario, skip
                continue;
            }
            
            try {
                $this->inventoryService->release(
                    item: $inventoryItem,
                    branchId: $order->branch_id,
                    quantity: (float) $item->quantity,
                    orderId: $order->id,
                    userId: $order->waiter_id
                );
            } catch (\Exception $e) {
                Log::warning('Error al devolver stock en cancelacion', [
                    'order_id' => $order->id,
                    'item' => $item->name_snapshot,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
