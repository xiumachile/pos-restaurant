<?php

namespace Modules\Cashier\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Cashier\Domain\Services\TipPayoutService;

class CashierServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TipPayoutService::class, function ($app) {
            return new TipPayoutService(
                $app->make(\Modules\Payments\Domain\Contracts\PaymentQueryServiceInterface::class)
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
