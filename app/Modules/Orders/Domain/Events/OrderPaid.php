<?php

namespace Modules\Orders\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Orders\Domain\Entities\Order;

/**
 * Se dispara cuando un pedido pasa de served → paid.
 * Los listeners deben:
 * - Registrar el pago en Billing
 * - Mover la mesa a billing
 */
class OrderPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order
    ) {}
}
