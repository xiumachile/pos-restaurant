<?php

namespace Modules\Tables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Orders\Domain\Entities\Order;
use Modules\Tables\Domain\Entities\RestaurantTable;

class TableReleased
{
    use Dispatchable;

    public function __construct(
        public RestaurantTable $table,
        public Order $order
    ) {
    }
}
