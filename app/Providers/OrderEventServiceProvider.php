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


/**
 * EventServiceProvider de Orders.
 *
 * F2.2: Eliminado UpdateTableOn* (movido a Tables/EventServiceProvider en F1.2a)
 * Los listeners de Tables ahora viven en su propio módulo, respetando encapsulamiento.
 *
 * ADR-022: Listeners de Inventory eliminados (módulo removido).
 * Los listeners de Recipes viven en RecipeEventServiceProvider.
 */
class OrderEventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        OrderConfirmed::class => [
            BroadcastOrderEvents::class . '@handleOrderConfirmed',
        ],
        OrderReady::class => [
            BroadcastOrderEvents::class . '@handleOrderReady',
        ],
        OrderPaid::class => [
            BroadcastOrderEvents::class . '@handleOrderPaid',
        ],
        OrderClosed::class => [
            // Vacío: Tables escucha OrderClosed en su propio provider
        ],
        OrderCancelled::class => [
            BroadcastOrderEvents::class . '@handleOrderCancelled',
            AuditOrderEvents::class . '@handleOrderCancelled',
        ],
        OrderDiscountApplied::class => [
        OrderItemRemoved::class => [
            AuditOrderEvents::class . '@handleOrderItemRemoved',
        ],
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
