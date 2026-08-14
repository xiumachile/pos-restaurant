<?php

namespace Modules\Printers\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Printers\Domain\Listeners\PrintKitchenOnOrderConfirm;
use Modules\Printers\Domain\Listeners\PrintReceiptOnOrderPaid;

class PrintersEventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderConfirmed::class => [
            PrintKitchenOnOrderConfirm::class,
        ],
        OrderPaid::class => [
            PrintReceiptOnOrderPaid::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
