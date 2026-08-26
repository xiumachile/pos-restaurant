<?php

namespace Modules\Payments\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Payments\Application\Services\PaymentQueryService;
use Modules\Payments\Application\Services\PaymentsExportService;
use Modules\Payments\Domain\Contracts\PaymentQueryServiceInterface;
use Modules\Payments\Domain\Contracts\PaymentsExportServiceInterface;

class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Contrato F1.4a: Cashier consulta pagos
        $this->app->singleton(
            PaymentQueryServiceInterface::class,
            PaymentQueryService::class
        );

        // Contrato F1.4c: Sync exporta métodos de pago
        $this->app->singleton(
            PaymentsExportServiceInterface::class,
            PaymentsExportService::class
        );
    }

    public function boot(): void
    {
        //
    }
}
