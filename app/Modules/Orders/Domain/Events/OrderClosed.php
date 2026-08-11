<?php

namespace Modules\Orders\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Orders\Domain\Entities\Order;

/**
 * Se dispara cuando un pedido pasa de paid → closed.
 * Los listeners deben:
 * - Liberar la mesa asociada (billing → available)
 * - Registrar en reportes
 */
class OrderClosed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order
    ) {}
}
