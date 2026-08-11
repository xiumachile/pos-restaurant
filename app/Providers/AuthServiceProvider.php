<?php

namespace App\Providers;

use App\Shared\Infrastructure\Auth\JwtUserProvider;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     */
    protected $policies = [];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Registrar provider personalizado para JWT
        // Este provider usa withoutGlobalScopes() al buscar usuarios,
        // evitando que el Global Scope de tenant interfiera durante autenticación.
        Auth::provider('jwt-eloquent', function ($app, array $config) {
            return new JwtUserProvider($app['hash'], $config['model']);
        });
    }
}
