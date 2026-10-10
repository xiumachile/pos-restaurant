<?php

namespace Modules\Orders\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Illuminate\Support\Str;

/**
 * Se dispara cuando un item es eliminado de un pedido.
 * 
 * HALLAZGO M-04: Trazabilidad completa de acciones sensibles.
 * Incluye event_uuid único para deduplicación y datos del item antes de su eliminación.
 */
class OrderItemRemoved
{
    use Dispatchable, SerializesModels;

    public string $event_uuid;

    public function __construct(
        public Order $order,
        public OrderItem $item,
        public string $reason,
        public int $userId
    ) {
        $this->event_uuid = (string) Str::uuid();
    }
}
