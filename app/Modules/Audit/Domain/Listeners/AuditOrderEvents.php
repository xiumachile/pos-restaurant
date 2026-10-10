<?php

namespace Modules\Audit\Domain\Listeners;

use Modules\Audit\Domain\Services\AuditService;
use Modules\Orders\Domain\Events\OrderCancelled;
use Modules\Orders\Domain\Events\OrderDiscountApplied;
use Modules\Orders\Domain\Events\OrderItemRemoved;

/**
 * Listener que registra eventos de Orders en el audit log.
 */
class AuditOrderEvents
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function handleOrderCancelled(OrderCancelled $event): void
    {
        $this->auditService->logOrderCancellation(
            order: $event->order,
            reason: $event->order->cancellation_reason ?? 'Sin especificar'
        );
    }

    public function handleOrderDiscountApplied(OrderDiscountApplied $event): void
    {
        $this->auditService->logDiscountApplied(
            order: $event->order,
            amount: $event->discountAmount,
            reason: $event->reason
        );
    }

    /**
     * HALLAZGO M-04: Registra la eliminación de un item del pedido.
     */
    public function handleOrderItemRemoved(OrderItemRemoved $event): void
    {
        $this->auditService->logOrderItemRemoved(
            order: $event->order,
            item: $event->item,
            reason: $event->reason,
            userId: $event->userId
        );
    }
}
