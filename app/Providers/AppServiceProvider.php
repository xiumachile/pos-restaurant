<?php

namespace App\Providers;

use App\Shared\Domain\Console\Commands\GenerateDailyReportCommand;


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
        // Registrar comandos Artisan custom
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateDailyReportCommand::class,
            ]);
        }

        //
        // Registrar listeners del módulo Recipes (BOM)
        \Illuminate\Support\Facades\Event::listen(
            OrderConfirmed::class,
            DeductRecipeOnOrderConfirm::class
        );

    }
}
