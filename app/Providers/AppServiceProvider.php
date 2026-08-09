<?php

namespace App\Providers;

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
    }
}
