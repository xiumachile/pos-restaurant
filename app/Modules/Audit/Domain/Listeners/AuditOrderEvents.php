<?php

namespace Modules\Audit\Domain\Listeners;

use Modules\Audit\Domain\Services\AuditService;
use Modules\Orders\Domain\Events\OrderCancelled;
use Modules\Orders\Domain\Events\OrderDiscountApplied;

/**
 * Listener que registra eventos de Orders en el audit log.
 */
class AuditOrderEvents
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Registra cancelación de orden.
     * OrderCancelled solo tiene $order; el reason está en $order->cancellation_reason
     */
    public function handleOrderCancelled(OrderCancelled $event): void
    {
        $this->auditService->logOrderCancellation(
            order: $event->order,
            reason: $event->order->cancellation_reason ?? 'Sin especificar'
        );
    }

    /**
     * Registra descuento aplicado.
     */
    public function handleOrderDiscountApplied(OrderDiscountApplied $event): void
    {
        $this->auditService->logDiscountApplied(
            order: $event->order,
            amount: $event->discountAmount,
            reason: $event->reason
        );
    }
}
