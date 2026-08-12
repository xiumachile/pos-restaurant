<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Orders\Domain\Events\OrderCancelled;
use Modules\Orders\Domain\Events\OrderClosed;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Orders\Domain\Listeners\UpdateTableOnCancel;
use Modules\Orders\Domain\Listeners\UpdateTableOnClose;
use Modules\Orders\Domain\Listeners\UpdateTableOnConfirm;
use Modules\Orders\Domain\Listeners\UpdateTableOnPaid;

class OrderEventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        OrderConfirmed::class => [
            UpdateTableOnConfirm::class,
        ],
        OrderPaid::class => [
            UpdateTableOnPaid::class,
        ],
        OrderClosed::class => [
            UpdateTableOnClose::class,
        ],
        OrderCancelled::class => [
            UpdateTableOnCancel::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
