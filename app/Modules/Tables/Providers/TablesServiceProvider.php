<?php

namespace Modules\Tables\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Tables\Application\Services\TablesExportService;
use Modules\Tables\Domain\Contracts\TablesExportServiceInterface;

class TablesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            TablesExportServiceInterface::class,
            TablesExportService::class
        );
    }
}
