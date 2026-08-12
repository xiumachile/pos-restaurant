<?php

namespace Modules\Orders\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Orders\Domain\Entities\Order;

/**
 * Se dispara cuando un pedido pasa de preparing → ready.
 * Los listeners deben:
 * - Notificar al garzón que el pedido está listo
 * - Actualizar el dashboard
 */
class OrderReady
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order
    ) {}
}
