<?php

namespace Modules\Audit\Domain\Listeners;

use Modules\Audit\Domain\Services\AuditService;
use Modules\Cashier\Domain\Events\DrawerOpened;

/**
 * Listener que registra eventos de Cashier en el audit log.
 */
class AuditCashierEvents
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Registra apertura de sesión de caja.
     */
    public function handleDrawerOpened(DrawerOpened $event): void
    {
        $this->auditService->log(
            action: 'drawer_opened',
            entityType: get_class($event->session),
            entityId: $event->session->id,
            entityUuid: $event->session->uuid ?? null,
            payload: [
                'session_number' => $event->session->session_number ?? null,
                'opening_amount' => $event->session->opening_amount ?? 0,
            ],
            reason: $event->reason
        );
    }
}
