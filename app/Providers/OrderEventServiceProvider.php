<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Kitchen\Domain\Listeners\BroadcastOrderEvents;
use Modules\Orders\Domain\Events\OrderCancelled;
use Modules\Audit\Domain\Listeners\AuditOrderEvents;
use Modules\Audit\Domain\Listeners\AuditCashierEvents;
use Modules\Orders\Domain\Events\OrderDiscountApplied;
use Modules\Cashier\Domain\Events\DrawerOpened;

use Modules\Orders\Domain\Events\OrderClosed;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Orders\Domain\Events\OrderReady;
use Modules\Orders\Domain\Listeners\UpdateTableOnCancel;
use Modules\Orders\Domain\Listeners\UpdateTableOnClose;
use Modules\Orders\Domain\Listeners\UpdateTableOnConfirm;
use Modules\Orders\Domain\Listeners\UpdateTableOnPaid;
use Modules\Inventory\Domain\Listeners\ReserveStockOnOrderConfirm;
use Modules\Inventory\Domain\Listeners\ReturnStockOnOrderCancel;

class OrderEventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        OrderConfirmed::class => [
            UpdateTableOnConfirm::class,
            BroadcastOrderEvents::class . '@handleOrderConfirmed',
            ReserveStockOnOrderConfirm::class,
        ],
        OrderReady::class => [
            BroadcastOrderEvents::class . '@handleOrderReady',
        ],
        OrderPaid::class => [
            UpdateTableOnPaid::class,
            BroadcastOrderEvents::class . '@handleOrderPaid',
        ],
        OrderClosed::class => [
            UpdateTableOnClose::class,
        ],
        OrderCancelled::class => [
            UpdateTableOnCancel::class,
            BroadcastOrderEvents::class . '@handleOrderCancelled',
            ReturnStockOnOrderCancel::class,
            AuditOrderEvents::class . '@handleOrderCancelled',
        ],
        OrderDiscountApplied::class => [
            AuditOrderEvents::class . '@handleOrderDiscountApplied',
        ],
        DrawerOpened::class => [
            AuditCashierEvents::class . '@handleDrawerOpened',
        ],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
