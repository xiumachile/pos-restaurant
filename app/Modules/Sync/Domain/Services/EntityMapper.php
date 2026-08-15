<?php

namespace Modules\Sync\Domain\Services;

use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;

/**
 * Mapea campos entre el esquema del servidor (Postgres)
 * y el esquema local (SQLite).
 * 
 * El esquema local es simplificado para optimizar almacenamiento
 * y rendimiento en el cliente desktop.
 */
class EntityMapper
{
    /**
     * Convierte una Order del servidor a formato local.
     */
    public function orderToLocal(Order $order): array
    {
        return [
            'uuid' => $order->uuid,
            'server_id' => $order->id,
            'branch_id' => $order->branch_id,
            'waiter_id' => $order->waiter_id,
            'order_number' => $order->order_number,
            'type' => $order->type?->value ?? 'dine_in',
            'status' => $order->status?->value ?? 'draft',
            'subtotal' => (float) $order->subtotal,
            'tax_amount' => (float) $order->tax_amount,
            'discount_amount' => (float) $order->discount_amount,
            'total' => (float) $order->total,
            'notes' => $order->notes,
            'version' => $order->version ?? 1,
            'sync_status' => $order->sync_status?->value ?? 'pending',
            'last_synced_at' => $order->last_synced_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Convierte datos locales a formato de Order del servidor.
     */
    public function localToOrder(array $localData): array
    {
        return [
            'uuid' => $localData['uuid'] ?? null,
            'branch_id' => $localData['branch_id'] ?? null,
            'waiter_id' => $localData['waiter_id'] ?? null,
            'order_number' => $localData['order_number'] ?? null,
            'type' => $localData['type'] ?? 'dine_in',
            'status' => $localData['status'] ?? 'draft',
            'subtotal' => $localData['subtotal'] ?? 0,
            'tax_amount' => $localData['tax_amount'] ?? 0,
            'discount_amount' => $localData['discount_amount'] ?? 0,
            'total' => $localData['total'] ?? 0,
            'notes' => $localData['notes'] ?? null,
            'version' => $localData['version'] ?? 1,
            'sync_status' => $localData['sync_status'] ?? 'pending',
        ];
    }

    /**
     * Convierte un OrderItem del servidor a formato local.
     */
    public function orderItemToLocal(OrderItem $item, int $localOrderId): array
    {
        return [
            'uuid' => $item->uuid,
            'server_id' => $item->id,
            'local_order_id' => $localOrderId,
            'name_snapshot' => $item->name_snapshot,
            'unit_price_snapshot' => (float) $item->unit_price_snapshot,
            'quantity' => $item->quantity,
            'subtotal' => (float) $item->subtotal,
            'tax_amount' => (float) ($item->tax_amount ?? 0),
            'notes' => $item->notes,
            'sync_status' => $item->sync_status?->value ?? 'pending',
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Convierte datos locales a formato de OrderItem del servidor.
     */
    public function localToOrderItem(array $localData): array
    {
        return [
            'uuid' => $localData['uuid'] ?? null,
            'name_snapshot' => $localData['name_snapshot'] ?? null,
            'unit_price_snapshot' => $localData['unit_price_snapshot'] ?? 0,
            'quantity' => $localData['quantity'] ?? 1,
            'subtotal' => $localData['subtotal'] ?? 0,
            'tax_amount' => $localData['tax_amount'] ?? 0,
            'notes' => $localData['notes'] ?? null,
            'sync_status' => $localData['sync_status'] ?? 'pending',
        ];
    }

    /**
     * Obtiene los campos que deben ignorarse en la sincronización.
     */
    public function getIgnoredFields(): array
    {
        return [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
            'sync_status',
            'version',
            'last_synced_at',
        ];
    }
}
