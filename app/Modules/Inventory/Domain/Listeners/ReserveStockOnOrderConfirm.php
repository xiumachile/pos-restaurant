<?php

namespace Modules\Inventory\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use Modules\Inventory\Domain\Services\InventoryService;
use Modules\Inventory\Domain\ValueObjects\StockMovementType;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Events\OrderConfirmed;

/**
 * Listener que reserva stock cuando un pedido es confirmado.
 * Se registra en OrderEventServiceProvider.
 */
class ReserveStockOnOrderConfirm
{
    public function __construct(
        private InventoryService $inventoryService
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        $order = $event->order;
        
        // Reservar stock para cada item del pedido
        foreach ($order->items as $item) {
            // TODO: En una implementacion completa, habria una tabla de recetas
            // que mapea menu_items a inventory_items con cantidades.
            // Por ahora, asumimos que cada menu_item tiene un inventory_item asociado
            // via un campo product->inventory_item_id o similar.
            
            // Ejemplo simplificado: buscar inventory_item por SKU del menu_item
            $inventoryItem = \Modules\Inventory\Domain\Entities\InventoryItem::where('company_id', $order->company_id)
                ->where('sku', $item->name_snapshot) // Asumiendo SKU = nombre del item
                ->first();
            
            if (!$inventoryItem) {
                // Item no tiene control de inventario, skip
                continue;
            }
            
            try {
                $this->inventoryService->reserve(
                    item: $inventoryItem,
                    branchId: $order->branch_id,
                    quantity: (float) $item->quantity,
                    orderId: $order->id,
                    userId: $order->waiter_id
                );
            } catch (InsufficientStockException $e) {
                Log::warning('Stock insuficiente al confirmar pedido', [
                    'order_id' => $order->id,
                    'item' => $item->name_snapshot,
                    'error' => $e->getMessage(),
                ]);
                // En produccion, podriamos revertir la transaccion
                // o enviar una notificacion al admin
            }
        }
    }
}
