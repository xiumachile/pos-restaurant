<?php

namespace Modules\Orders\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Orders\Domain\Entities\Order;

/**
 * Se dispara cuando un pedido es cancelado desde cualquier estado activo.
 * Los listeners deben:
 * - Liberar la mesa si estaba ocupada
 * - Devolver inventario reservado (Fase 7)
 */
class OrderCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order
    ) {}
}
