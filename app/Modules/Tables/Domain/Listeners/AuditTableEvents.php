<?php

namespace Modules\Tables\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Tables\Domain\Events\TableBillingRequested;
use Modules\Tables\Domain\Events\TableCleaningCompleted;
use Modules\Tables\Domain\Events\TableCleaningStarted;
use Modules\Tables\Domain\Events\TableHeld;
use Modules\Tables\Domain\Events\TableOccupied;
use Modules\Tables\Domain\Events\TableOutOfService;
use Modules\Tables\Domain\Events\TableReleased;

/**
 * Registra todos los eventos de mesa en el log de auditoría.
 * F1.2B: Listener observador que no modifica estado.
 */
class AuditTableEvents
{
    public function handleOccupied(TableOccupied $event): void
    {
        Log::info('[AuditTable] Mesa ocupada', [
            'table_id' => $event->table->id,
            'table_number' => $event->table->table_number,
            'order_id' => $event->order->id ?? null,
        ]);
    }

    public function handleReleased(TableReleased $event): void
    {
        Log::info('[AuditTable] Mesa liberada', [
            'table_id' => $event->table->id,
            'table_number' => $event->table->table_number,
        ]);
    }

    public function handleBillingRequested(TableBillingRequested $event): void
    {
        Log::info('[AuditTable] Cuenta solicitada', [
            'table_id' => $event->table->id,
            'table_number' => $event->table->table_number,
        ]);
    }

    public function handleCleaningStarted(TableCleaningStarted $event): void
    {
        Log::info('[AuditTable] Limpieza iniciada', [
            'table_id' => $event->table->id,
            'table_number' => $event->table->table_number,
            'reason' => $event->reason,
        ]);
    }

    public function handleCleaningCompleted(TableCleaningCompleted $event): void
    {
        Log::info('[AuditTable] Limpieza completada', [
            'table_id' => $event->table->id,
            'table_number' => $event->table->table_number,
        ]);
    }

    public function handleHeld(TableHeld $event): void
    {
        Log::info('[AuditTable] Mesa reservada', [
            'table_id' => $event->table->id,
            'customer' => $event->customerName,
            'hold_minutes' => $event->holdMinutes,
        ]);
    }

    public function handleOutOfService(TableOutOfService $event): void
    {
        Log::warning('[AuditTable] Mesa fuera de servicio', [
            'table_id' => $event->table->id,
            'reason' => $event->reason,
        ]);
    }
}
