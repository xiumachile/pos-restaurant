<?php

namespace Modules\Recipes\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Recipes\Domain\Listeners\DeductRecipeOnOrderConfirm;

class RecipeEventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        OrderConfirmed::class => [
            DeductRecipeOnOrderConfirm::class,
        ],
    ];

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
