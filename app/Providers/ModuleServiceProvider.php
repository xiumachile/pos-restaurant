<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    protected array $modules = [
        'Identity', 'Companies', 'Branches', 'Catalog', 'Tables',
        'Orders', 'Kitchen', 'Billing', 'Payments',
        'Recipes', 'Cashier',
        'Inventory', 'Customers', 'Delivery', 'Reports',
        'Integrations', 'I18n', 'Sync',
    ];

    public function register(): void {}

    public function boot(): void
    {
        foreach ($this->modules as $module) {
            $this->loadModuleRoutes($module);
            $this->loadModuleMigrations($module);
        }
    }

    protected function loadModuleRoutes(string $module): void
    {
        $routeFile = app_path("Modules/{$module}/Routes/api.php");
        if (file_exists($routeFile)) {
            Route::middleware('api')->prefix('api')->group($routeFile);
        }
    }

    protected function loadModuleMigrations(string $module): void
    {
        $path = app_path("Modules/{$module}/Database/Migrations");
        if (is_dir($path)) {
            $this->loadMigrationsFrom($path);
        }
    }
}
