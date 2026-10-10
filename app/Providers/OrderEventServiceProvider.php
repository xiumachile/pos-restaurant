<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Audit\Domain\Listeners\AuditCashierEvents;
use Modules\Audit\Domain\Listeners\AuditOrderEvents;
use Modules\Cashier\Domain\Events\DrawerOpened;
use Modules\Kitchen\Domain\Listeners\BroadcastOrderEvents;
use Modules\Orders\Domain\Events\OrderCancelled;
use Modules\Orders\Domain\Events\OrderClosed;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Orders\Domain\Events\OrderDiscountApplied;
use Modules\Orders\Domain\Events\OrderItemRemoved;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Orders\Domain\Events\OrderPreparationStarted;
use Modules\Orders\Domain\Events\OrderReady;
use Modules\Orders\Domain\Events\OrderServed;

class OrderEventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderConfirmed::class => [
            BroadcastOrderEvents::class . '@handleOrderConfirmed',
        ],
        OrderPreparationStarted::class => [
            BroadcastOrderEvents::class . '@handleOrderPreparationStarted',
        ],
        OrderReady::class => [
            BroadcastOrderEvents::class . '@handleOrderReady',
        ],
        OrderServed::class => [
            BroadcastOrderEvents::class . '@handleOrderServed',
        ],
        OrderPaid::class => [
            BroadcastOrderEvents::class . '@handleOrderPaid',
        ],
        OrderClosed::class => [
            // Tables escucha OrderClosed en su propio provider
        ],
        OrderCancelled::class => [
            BroadcastOrderEvents::class . '@handleOrderCancelled',
            AuditOrderEvents::class . '@handleOrderCancelled',
        ],
        OrderDiscountApplied::class => [
            AuditOrderEvents::class . '@handleOrderDiscountApplied',
        ],
        OrderItemRemoved::class => [
            AuditOrderEvents::class . '@handleOrderItemRemoved',
        ],
        DrawerOpened::class => [
            AuditCashierEvents::class . '@handleDrawerOpened',
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
