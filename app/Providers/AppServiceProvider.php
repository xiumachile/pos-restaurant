<?php

namespace App\Providers;

use App\Shared\Domain\Console\Commands\GenerateDailyReportCommand;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Recipes\Domain\Listeners\DeductRecipeOnOrderConfirm;
use Illuminate\Support\ServiceProvider;
use App\Shared\Application\TenantContext;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Servicio de contexto de tenant (singleton por request)
        $this->app->scoped(TenantContext::class, function ($app) {
            return new TenantContext();
        });
    }

    public function boot(): void
    {
        // Registrar comandos Artisan custom
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateDailyReportCommand::class,
            ]);
        }

        // Registrar listeners del módulo Recipes (BOM)
        \Illuminate\Support\Facades\Event::listen(
            OrderConfirmed::class,
            DeductRecipeOnOrderConfirm::class
        );

        // ═══════════════════════════════════════════════════════════
        // HALLAZGO 09: Validación de seguridad de Broadcasting
        // ═══════════════════════════════════════════════════════════
        $this->validateBroadcastingConfig();
    }

    /**
     * Valida que la configuración de broadcasting sea segura.
     * Fail-fast en producción si hay configuración insegura.
     * 
     * Verifica:
     * 1. BROADCAST_CONNECTION no debe ser 'null' o 'log' en producción
     * 2. allowed_origins no debe contener '*' en producción
     * 3. rate_limiting debe estar habilitado en producción
     */
    private function validateBroadcastingConfig(): void
    {
        $env = $this->app->environment();
        
        // Solo validar en producción y staging
        if (!in_array($env, ['production', 'staging'], true)) {
            return;
        }

        $broadcastDriver = config('broadcasting.default');
        
        // 1. Verificar que broadcasting esté activo
        if (in_array($broadcastDriver, ['null', 'log'], true)) {
            $message = "[Hallazgo 09] BROADCAST_CONNECTION está deshabilitado en {$env}. " .
                       "El KDS no recibirá eventos. Configura BROADCAST_CONNECTION=reverb";
            
            Log::error($message);
            
            // Fail-fast en producción real
            if ($env === 'production') {
                throw new \RuntimeException($message);
            }
        }

        // 2. Verificar allowed_origins (no wildcard)
        $allowedOrigins = config('reverb.apps.0.allowed_origins', []);
        if (in_array('*', $allowedOrigins, true)) {
            $message = "[Hallazgo 09] Reverb allowed_origins contiene '*'. " .
                       "Configura BROADCAST_ALLOWED_ORIGINS con dominios específicos.";
            
            Log::error($message);
            
            if ($env === 'production') {
                throw new \RuntimeException($message);
            }
        }

        // 3. Verificar que allowed_origins no esté vacío
        if (empty($allowedOrigins)) {
            $message = "[Hallazgo 09] Reverb allowed_origins está vacío. " .
                       "Configura BROADCAST_ALLOWED_ORIGINS con al menos el dominio de la app.";
            
            Log::error($message);
        }

        // 4. Verificar rate_limiting habilitado
        $rateLimitingEnabled = config('reverb.apps.0.rate_limiting.enabled', false);
        if (!$rateLimitingEnabled) {
            $message = "[Hallazgo 09] Reverb rate_limiting está deshabilitado en {$env}. " .
                       "Esto permite DoS sobre el servidor WebSocket.";
            
            Log::warning($message);
        }

        // 5. Verificar max_connections razonable
        $maxConnections = config('reverb.apps.0.max_connections');
        if ($maxConnections === null || $maxConnections > 10000) {
            Log::warning(
                "[Hallazgo 09] Reverb max_connections no está configurado o es muy alto ({$maxConnections}). " .
                "Considera limitar a un valor razonable."
            );
        }

        Log::info("[Hallazgo 09] Validación de broadcasting pasada correctamente", [
            'driver' => $broadcastDriver,
            'allowed_origins_count' => count($allowedOrigins),
            'rate_limiting_enabled' => $rateLimitingEnabled,
            'max_connections' => $maxConnections,
        ]);
    }
}
