<?php

namespace Modules\Tables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Orders\Domain\Entities\Order;
use Modules\Tables\Domain\Entities\RestaurantTable;

/**
 * Evento emitido cuando una mesa es ocupada por un pedido.
 * Creado en F1.2a como parte del refactor de listeners.
 */
class TableOccupied
{
    use Dispatchable;

    public function __construct(
        public RestaurantTable $table,
        public Order $order
    ) {
    }
}
