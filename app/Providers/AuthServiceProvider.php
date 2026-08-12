<?php

namespace App\Providers;

use App\Shared\Infrastructure\Auth\JwtUserProvider;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Policies\OrderPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     */
    protected $policies = [
        Order::class => OrderPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Registrar provider personalizado para JWT
        Auth::provider('jwt-eloquent', function ($app, array $config) {
            return new JwtUserProvider($app['hash'], $config['model']);
        });

        // Registrar policies
        $this->registerPolicies();
    }
}
