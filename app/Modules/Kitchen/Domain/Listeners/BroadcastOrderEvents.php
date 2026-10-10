<?php

namespace Modules\Kitchen\Domain\Listeners;

use Modules\Kitchen\Domain\Events\BroadcastOrderCancelled;
use Modules\Kitchen\Domain\Events\BroadcastOrderConfirmed;
use Modules\Kitchen\Domain\Events\BroadcastOrderPaid;
use Modules\Kitchen\Domain\Events\BroadcastOrderPreparationStarted;
use Modules\Kitchen\Domain\Events\BroadcastOrderReady;
use Modules\Kitchen\Domain\Events\BroadcastOrderServed;
use Modules\Orders\Domain\Events\OrderCancelled;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Orders\Domain\Events\OrderPreparationStarted;
use Modules\Orders\Domain\Events\OrderReady;
use Modules\Orders\Domain\Events\OrderServed;

/**
 * Listener que convierte eventos de dominio en eventos de broadcast para el KDS.
 * 
 * HALLAZGO C-01: Ahora cubre todas las transiciones operacionales significativas
 * para garantizar que el KDS reciba señales uniformes de cada cambio de estado.
 */
class BroadcastOrderEvents
{
    public function handleOrderConfirmed(OrderConfirmed $event): void
    {
        BroadcastOrderConfirmed::dispatch($event->order);
    }

    public function handleOrderPreparationStarted(OrderPreparationStarted $event): void
    {
        BroadcastOrderPreparationStarted::dispatch($event->order);
    }

    public function handleOrderReady(OrderReady $event): void
    {
        BroadcastOrderReady::dispatch($event->order);
    }

    public function handleOrderServed(OrderServed $event): void
    {
        BroadcastOrderServed::dispatch($event->order);
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
