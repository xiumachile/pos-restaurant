<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Módulos activos del sistema (sección 7.1).
     * A medida que avances por fases, cada módulo aportará sus rutas.
     */
    protected array $modules = [
        'Identity',
        'Companies',
        'Branches',
        'Catalog',
        'Tables',
        'Orders',
        'Kitchen',
        'Billing',
        'Payments',
        'Cashier',
        'Inventory',
        'Customers',
        'Delivery',
        'Reports',
        'Integrations',
        'I18n',
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ($this->modules as $module) {
            $this->loadModuleRoutes($module);
            $this->loadModuleMigrations($module);
        }
    }

    protected function loadModuleRoutes(string $module): void
    {
        $routeFile = base_path("Modules/{$module}/Routes/api.php");

        if (file_exists($routeFile)) {
            Route::middleware('api')
                ->prefix('api')
                ->group($routeFile);
        }
    }

    protected function loadModuleMigrations(string $module): void
    {
        $path = base_path("Modules/{$module}/Database/Migrations");

        if (is_dir($path)) {
            $this->loadMigrationsFrom($path);
        }
    }
}
