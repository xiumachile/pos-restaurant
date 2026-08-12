<?php

namespace Modules\Inventory\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Domain\Entities\InventoryItem;
use Modules\Inventory\Domain\Entities\InventoryStock;
use Modules\Inventory\Domain\Entities\StockMovement;
use Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use Modules\Inventory\Domain\ValueObjects\StockMovementType;

/**
 * Servicio de dominio para gestión de inventario.
 * Maneja reserva, liberación y consumo de stock con trazabilidad.
 */
class InventoryService
{
    /**
     * Registra una entrada de stock (compra, devolución, ajuste positivo).
     */
    public function recordMovement(
        InventoryItem $item,
        int $branchId,
        StockMovementType $type,
        float $quantity,
        ?int $userId = null,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): StockMovement {
        return DB::transaction(function () use ($item, $branchId, $type, $quantity, $userId, $reason, $referenceType, $referenceId) {
            return StockMovement::record(
                companyId: $item->company_id,
                branchId: $branchId,
                inventoryItemId: $item->id,
                type: $type,
                quantity: $quantity,
                referenceType: $referenceType,
                referenceId: $referenceId,
                userId: $userId,
                reason: $reason
            );
        });
    }

    /**
     * Reserva stock para un pedido.
     * Lanza InsufficientStockException si no hay stock suficiente.
     */
    public function reserve(
        InventoryItem $item,
        int $branchId,
        float $quantity,
        int $orderId,
        ?int $userId = null
    ): StockMovement {
        return DB::transaction(function () use ($item, $branchId, $quantity, $orderId, $userId) {
            // Verificar stock disponible con lock
            $stock = InventoryStock::where('branch_id', $branchId)
                ->where('inventory_item_id', $item->id)
                ->lockForUpdate()
                ->first();

            $available = (float) ($stock?->quantity ?? 0);

            if ($available < $quantity) {
                throw InsufficientStockException::forItem(
                    $item->name_translations['es'] ?? 'Item',
                    $quantity,
                    $available
                );
            }

            return StockMovement::record(
                companyId: $item->company_id,
                branchId: $branchId,
                inventoryItemId: $item->id,
                type: StockMovementType::OUT_RESERVATION,
                quantity: $quantity,
                referenceType: 'order',
                referenceId: $orderId,
                userId: $userId,
                reason: 'Reserva para pedido'
            );
        });
    }

    /**
     * Libera stock reservado (cuando se cancela un pedido).
     */
    public function release(
        InventoryItem $item,
        int $branchId,
        float $quantity,
        int $orderId,
        ?int $userId = null
    ): StockMovement {
        return DB::transaction(function () use ($item, $branchId, $quantity, $orderId, $userId) {
            return StockMovement::record(
                companyId: $item->company_id,
                branchId: $branchId,
                inventoryItemId: $item->id,
                type: StockMovementType::IN_RETURN,
                quantity: $quantity,
                referenceType: 'order',
                referenceId: $orderId,
                userId: $userId,
                reason: 'Liberación por cancelación de pedido'
            );
        });
    }

    /**
     * Verifica si hay stock suficiente para una cantidad dada.
     */
    public function hasEnoughStock(InventoryItem $item, int $branchId, float $quantity): bool
    {
        $stock = InventoryStock::where('branch_id', $branchId)
            ->where('inventory_item_id', $item->id)
            ->value('quantity');

        return (float) ($stock ?? 0) >= $quantity;
    }

    /**
     * Obtiene el stock actual de un item en una sucursal.
     */
    public function getCurrentStock(InventoryItem $item, int $branchId): float
    {
        return (float) (InventoryStock::where('branch_id', $branchId)
            ->where('inventory_item_id', $item->id)
            ->value('quantity') ?? 0);
    }
}
