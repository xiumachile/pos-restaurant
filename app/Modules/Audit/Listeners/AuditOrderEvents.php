<?php

namespace Modules\Audit\Listeners;

use Modules\Audit\Domain\Services\AuditService;
use Modules\Orders\Events\OrderCancelled;
use Modules\Orders\Events\OrderDiscountApplied;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Listener que registra eventos de Orders en el audit log.
 */
class AuditOrderEvents implements ShouldQueue
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Registra cancelación de orden.
     */
    public function handleOrderCancelled(OrderCancelled $event): void
    {
        $this->auditService->logOrderCancellation(
            order: $event->order,
            reason: $event->reason ?? 'Sin especificar'
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
            reason: $event->reason ?? 'Descuento manual'
        );
    }
}
