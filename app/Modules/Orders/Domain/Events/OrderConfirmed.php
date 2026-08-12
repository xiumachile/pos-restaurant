<?php

namespace Modules\Orders\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Orders\Domain\Entities\Order;

/**
 * Se dispara cuando un pedido pasa de draft → confirmed.
 * Los listeners deben:
 * - Ocupar la mesa asociada (si es dine_in)
 * - Reservar inventario (Fase 7)
 * - Notificar a cocina (Fase 6)
 */
class OrderConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order
    ) {}
}
