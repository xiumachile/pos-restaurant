<?php

namespace Modules\Tables\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Orders\Domain\Events\OrderCancelled;
use Modules\Orders\Domain\Events\OrderClosed;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Tables\Domain\Events\TableBillingRequested;
use Modules\Tables\Domain\Events\TableCleaningCompleted;
use Modules\Tables\Domain\Events\TableCleaningStarted;
use Modules\Tables\Domain\Events\TableHeld;
use Modules\Tables\Domain\Events\TableOccupied;
use Modules\Tables\Domain\Events\TableOutOfService;
use Modules\Tables\Domain\Events\TableReleased;
use Modules\Tables\Domain\Listeners\AuditTableEvents;
use Modules\Tables\Domain\Listeners\OccupyTableOnOrderConfirm;
use Modules\Tables\Domain\Listeners\ReleaseTableOnOrderCancel;
use Modules\Tables\Domain\Listeners\ReleaseTableOnOrderClose;
use Modules\Tables\Domain\Listeners\ReleaseTableOnOrderPaid;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // ========================================
        // Orders → Tables (F1.2a)
        // Tables reacciona a eventos de Orders
        // ========================================
        OrderConfirmed::class => [
            OccupyTableOnOrderConfirm::class,
        ],
        OrderCancelled::class => [
            ReleaseTableOnOrderCancel::class,
        ],
        OrderClosed::class => [
            ReleaseTableOnOrderClose::class,
        ],
        OrderPaid::class => [
            ReleaseTableOnOrderPaid::class,
        ],

        // ========================================
        // Tables → Audit (F1.2b)
        // Audit registra todos los eventos de Tables
        // ========================================
        TableOccupied::class => [
            [AuditTableEvents::class, 'handleOccupied'],
        ],
        TableReleased::class => [
            [AuditTableEvents::class, 'handleReleased'],
        ],
        TableBillingRequested::class => [
            [AuditTableEvents::class, 'handleBillingRequested'],
        ],
        TableCleaningStarted::class => [
            [AuditTableEvents::class, 'handleCleaningStarted'],
        ],
        TableCleaningCompleted::class => [
            [AuditTableEvents::class, 'handleCleaningCompleted'],
        ],
        TableHeld::class => [
            [AuditTableEvents::class, 'handleHeld'],
        ],
        TableOutOfService::class => [
            [AuditTableEvents::class, 'handleOutOfService'],
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
