<?php

namespace Modules\Audit\Domain\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Audit\Domain\Entities\AuditLog;
use Throwable;

/**
 * Servicio central de auditoría.
 *
 * Registra eventos de auditoría de forma inmutable.
 * Nunca debe fallar el flujo principal si el logging falla.
 *
 * Principio arquitectónico #8: Todas las acciones críticas deben
 * registrarse de forma inmutable.
 */
class AuditService
{
    public function log(
        string $action,
        string $entityType,
        int $entityId,
        ?string $entityUuid = null,
        ?array $payload = null,
        ?array $changes = null,
        ?string $reason = null
    ): ?AuditLog {
        try {
            $user = auth()->user();

            $companyId = $user?->company_id;
            $branchId = $user?->branch_id;

            return AuditLog::create([
                'uuid' => (string) Str::uuid(),
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'entity_uuid' => $entityUuid,
                'payload' => $payload,
                'changes' => $changes,
                'reason' => $reason,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'occurred_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('AuditService: Failed to log event', [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function logOrderCancellation($order, ?string $reason = null): ?AuditLog
    {
        return $this->log(
            action: 'order_cancelled',
            entityType: get_class($order),
            entityId: $order->id,
            entityUuid: $order->uuid ?? null,
            payload: [
                'order_number' => $order->order_number,
                'total' => $order->total,
                'status' => $order->status?->value ?? 'unknown',
            ],
            reason: $reason
        );
    }

    public function logDiscountApplied($order, float $amount, ?string $reason = null): ?AuditLog
    {
        return $this->log(
            action: 'discount_applied',
            entityType: get_class($order),
            entityId: $order->id,
            entityUuid: $order->uuid ?? null,
            payload: [
                'order_number' => $order->order_number,
                'discount_amount' => $amount,
            ],
            reason: $reason
        );
    }

    /**
     * HALLAZGO M-04: Registra la eliminación de un item de un pedido.
     */
    public function logOrderItemRemoved($order, $item, string $reason, int $userId): ?AuditLog
    {
        return $this->log(
            action: 'order_item_removed',
            entityType: get_class($item),
            entityId: $item->id,
            entityUuid: $item->uuid ?? null,
            payload: [
                'order_uuid' => $order->uuid,
                'order_number' => $order->order_number,
                'product_id' => $item->product_id,
                'product_name' => $item->name_snapshot,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price_snapshot,
                'subtotal_affected' => $item->subtotal,
                'user_id' => $userId,
            ],
            changes: [
                'quantity' => ['before' => $item->quantity, 'after' => 0],
                'status' => ['before' => 'active', 'after' => 'removed'],
            ],
            reason: $reason
        );
    }

    public function logDrawerOpened($cashRegister, ?string $reason = null): ?AuditLog
    {
        return $this->log(
            action: 'drawer_opened',
            entityType: get_class($cashRegister),
            entityId: $cashRegister->id,
            entityUuid: $cashRegister->uuid ?? null,
            payload: [
                'register_code' => $cashRegister->code ?? null,
                'register_name' => $cashRegister->name ?? null,
            ],
            reason: $reason
        );
    }

    public function logPriceChanged($entity, float $oldPrice, float $newPrice, ?string $reason = null): ?AuditLog
    {
        return $this->log(
            action: 'price_changed',
            entityType: get_class($entity),
            entityId: $entity->id,
            entityUuid: $entity->uuid ?? null,
            changes: [
                'price' => [
                    'before' => $oldPrice,
                    'after' => $newPrice,
                ],
            ],
            reason: $reason
        );
    }
}
