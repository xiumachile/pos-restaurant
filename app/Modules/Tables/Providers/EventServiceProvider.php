<?php

namespace Modules\Tables\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Orders\Domain\Events\OrderCancelled;
use Modules\Orders\Domain\Events\OrderClosed;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Tables\Domain\Listeners\OccupyTableOnOrderConfirm;
use Modules\Tables\Domain\Listeners\ReleaseTableOnOrderCancel;
use Modules\Tables\Domain\Listeners\ReleaseTableOnOrderClose;
use Modules\Tables\Domain\Listeners\ReleaseTableOnOrderPaid;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderConfirmed::class => [
            OccupyTableOnOrderConfirm::class,
        ],
        OrderCancelled::class => [
            ReleaseTableOnOrderCancel::class,
        ],
        OrderClosed::class => [
            ReleaseTableOnOrderClose::class,
        ],
        OrderPaid::class => [
            ReleaseTableOnOrderPaid::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
