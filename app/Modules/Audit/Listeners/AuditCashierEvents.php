<?php

namespace Modules\Audit\Listeners;

use Modules\Audit\Domain\Services\AuditService;
use Modules\Cashier\Events\DrawerOpened;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Listener que registra eventos de Cashier en el audit log.
 */
class AuditCashierEvents implements ShouldQueue
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Registra apertura de cajón.
     */
    public function handleDrawerOpened(DrawerOpened $event): void
    {
        $this->auditService->logDrawerOpened(
            cashRegister: $event->cashRegister,
            reason: $event->reason ?? 'Apertura de sesión'
        );
    }
}
