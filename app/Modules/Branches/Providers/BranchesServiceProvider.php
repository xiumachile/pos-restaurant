<?php

namespace Modules\Branches\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Branches\Application\Services\BranchQueryService;
use Modules\Branches\Domain\Contracts\BranchQueryServiceInterface;

class BranchesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registrar el contrato como singleton
        $this->app->singleton(
            BranchQueryServiceInterface::class,
            BranchQueryService::class
        );
    }

    public function boot(): void
    {
        //
    }
}
