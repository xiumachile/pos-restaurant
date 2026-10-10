<?php

namespace Modules\Orders\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Orders\Domain\Entities\Order;
use Illuminate\Support\Str;

/**
 * Se dispara cuando un pedido pasa de draft → confirmed.
 * 
 * HALLAZGO M-03: Incluye event_uuid único para que los listeners (KDS, ocupación de mesa)
 * puedan deduplicar el procesamiento en caso de reintentos o reconexiones.
 */
class OrderConfirmed
{
    use Dispatchable, SerializesModels;

    public string $event_uuid;

    public function __construct(
        public Order $order
    ) {
        // UUID único por instancia del evento para deduplicación idempotente
        $this->event_uuid = (string) Str::uuid();
    }
}
