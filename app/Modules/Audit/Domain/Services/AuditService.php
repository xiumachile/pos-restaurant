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
    /**
     * Registra un evento de auditoría.
     */
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
            
            // Obtener company_id y branch_id del usuario autenticado
            // (más confiable que TenantContext en tests)
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
            // Nunca fallar el flujo principal por auditoría
            Log::error('AuditService: Failed to log event', [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return null;
        }
    }

    /**
     * Registra una cancelación de orden.
     */
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

    /**
     * Registra un descuento aplicado.
     */
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
     * Registra una apertura de cajón.
     */
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

    /**
     * Registra un cambio de precio.
     */
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
