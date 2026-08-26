<?php

namespace Modules\Catalog\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Catalog\Application\Services\CatalogExportService;
use Modules\Catalog\Domain\Contracts\CatalogExportServiceInterface;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            CatalogExportServiceInterface::class,
            CatalogExportService::class
        );
    }
}
