<?php

namespace Modules\Kitchen\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Orders\Domain\Entities\Order;

class BroadcastOrderServed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order
    ) {}
}
