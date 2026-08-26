<?php

namespace Modules\Payments\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Payments\Application\Services\PaymentQueryService;
use Modules\Payments\Domain\Contracts\PaymentQueryServiceInterface;

class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registrar el contrato como singleton
        $this->app->singleton(
            PaymentQueryServiceInterface::class,
            PaymentQueryService::class
        );
    }

    public function boot(): void
    {
        //
    }
}
