<?php

namespace Modules\Kitchen\Domain\Listeners;

use Modules\Kitchen\Domain\Events\BroadcastOrderCancelled;
use Modules\Kitchen\Domain\Events\BroadcastOrderConfirmed;
use Modules\Kitchen\Domain\Events\BroadcastOrderPaid;
use Modules\Kitchen\Domain\Events\BroadcastOrderReady;
use Modules\Orders\Domain\Events\OrderCancelled;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Orders\Domain\Events\OrderReady;

/**
 * Listener que convierte eventos de dominio en eventos de broadcast.
 * Se registra en OrderEventServiceProvider.
 */
class BroadcastOrderEvents
{
    public function handleOrderConfirmed(OrderConfirmed $event): void
    {
        BroadcastOrderConfirmed::dispatch($event->order);
    }

    public function handleOrderReady(OrderReady $event): void
    {
        BroadcastOrderReady::dispatch($event->order);
    }

    public function handleOrderCancelled(OrderCancelled $event): void
    {
        BroadcastOrderCancelled::dispatch($event->order);
    }

    public function handleOrderPaid(OrderPaid $event): void
    {
        BroadcastOrderPaid::dispatch($event->order);
    }
}
