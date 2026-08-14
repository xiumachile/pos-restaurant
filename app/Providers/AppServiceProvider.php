<?php

namespace App\Providers;

use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Recipes\Domain\Listeners\DeductRecipeOnOrderConfirm;
use Illuminate\Support\ServiceProvider;
use App\Shared\Application\TenantContext;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Servicio de contexto de tenant (singleton por request)
        $this->app->scoped(TenantContext::class, function ($app) {
            return new TenantContext();
        });
    }

    public function boot(): void
    {
        //
        // Registrar listeners del módulo Recipes (BOM)
        \Illuminate\Support\Facades\Event::listen(
            OrderConfirmed::class,
            DeductRecipeOnOrderConfirm::class
        );

    }
}
