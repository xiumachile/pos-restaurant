<?php

namespace Modules\Tables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Orders\Domain\Entities\Order;
use Modules\Tables\Domain\Entities\RestaurantTable;

/**
 * Emitido cuando un cliente solicita la cuenta.
 * Transición: SERVING → CHECK_REQUESTED
 *
 * Receptores esperados:
 * - Cashier: prepara bill para pago
 * - Kitchen: notifica fin de servicio
 * - Audit: registra solicitud
 */
class TableBillingRequested
{
    use Dispatchable;

    public function __construct(
        public RestaurantTable $table,
        public ?Order $order = null
    ) {
    }
}
