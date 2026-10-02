<?php

namespace Modules\Catalog\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Catalog\Application\Services\CatalogExportService;
use Modules\Catalog\Domain\Contracts\CatalogExportServiceInterface;
use Modules\Catalog\Domain\Observers\BranchObserver;
use Modules\Catalog\Console\EnsureDefaultMenuCommand;
use Modules\Branches\Domain\Entities\Branch;


class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            CatalogExportServiceInterface::class,
            CatalogExportService::class
        );
    }

    public function boot(): void
    {
        // Provisioning automático de defaults para nuevas sucursales
        Branch::observe(BranchObserver::class);

        // Comando de reparación de defaults
        $this->commands([
            EnsureDefaultMenuCommand::class,
        ]);
    }

}
