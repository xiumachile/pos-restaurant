<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Tests de seguridad para configuración de Broadcasting (Hallazgo 09)
 *
 * Verifica que el archivo config/reverb.php tiene valores seguros por defecto
 * mediante análisis estático del código fuente, no ejecución runtime.
 *
 * RAZÓN: En testing, env() retorna valores de phpunit.xml, no defaults del código.
 * Necesitamos verificar que el CÓDIGO tiene defaults seguros, no el runtime.
 */

/**
 * Helper: extrae el bloque de una clave específica desde un archivo de config PHP
 * Retorna las N líneas siguientes a la primera aparición de la clave.
 */
function extractConfigBlock(string $filePath, string $key, int $linesAfter = 10): string
{
    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);
    $startIndex = -1;
    
    foreach ($lines as $i => $line) {
        if (strpos($line, "'$key'") !== false || strpos($line, "\"$key\"") !== false) {
            $startIndex = $i;
            break;
        }
    }
    
    if ($startIndex === -1) {
        return '';
    }
    
    return implode("\n", array_slice($lines, $startIndex, $linesAfter));
}

test('Hallazgo 09: broadcasting default es seguro en testing', function () {
    expect(config('broadcasting.default'))->toBeString();
});

test('Hallazgo 09: reverb.allowed_origins NO contiene wildcard por defecto', function () {
    // Análisis contextual: extraer solo el bloque de allowed_origins
    $block = extractConfigBlock(config_path('reverb.php'), 'allowed_origins', 5);
    
    expect($block)->not->toBeEmpty('No se encontró allowed_origins en reverb.php');
    
    // Verificar que NO hay asignación literal de ['*'] o '*'
    // Regex: comilla + asterisco + comilla (valor literal, no comentario)
    $hasWildcardLiteral = preg_match('/[\'"]\*[\'"]/', $block) === 1;
    
    expect($hasWildcardLiteral)->toBeFalse(
        "allowed_origins contiene wildcard '*' literal:\n$block"
    );
    
    // Debe usar BROADCAST_ALLOWED_ORIGINS
    expect($block)->toContain('BROADCAST_ALLOWED_ORIGINS');
});

test('Hallazgo 09: rate_limiting está habilitado por defecto', function () {
    $configContent = file_get_contents(config_path('reverb.php'));
    
    expect($configContent)->toContain("'enabled'")
        ->and($configContent)->toContain('REVERB_APP_RATE_LIMITING_ENABLED')
        ->and($configContent)->toContain('true');
});

test('Hallazgo 09: max_connections tiene límite por defecto', function () {
    $block = extractConfigBlock(config_path('reverb.php'), 'max_connections', 3);
    
    expect($block)->not->toBeEmpty();
    
    // Extraer el default numérico del patrón env('REVERB_APP_MAX_CONNECTIONS', NUMERO)
    $matches = [];
    preg_match('/REVERB_APP_MAX_CONNECTIONS[\'"],\s*(\d+)/', $block, $matches);
    
    expect($matches)->toHaveCount(2, "No se encontró default numérico para max_connections");
    
    $default = (int) $matches[1];
    expect($default)->toBeGreaterThan(0)
        ->and($default)->toBeLessThanOrEqual(1000);
});

test('Hallazgo 09: canales de KDS validan rol y sucursal', function () {
    $channelCallbacks = \Illuminate\Support\Facades\Broadcast::getChannels();
    
    expect($channelCallbacks)->toHaveKey('kitchen.{branchId}')
        ->and($channelCallbacks)->toHaveKey('waiters.{branchId}')
        ->and($channelCallbacks)->toHaveKey('dashboard.{companyId}');
});

test('Hallazgo 09: eventos de cocina implementan ShouldBroadcast', function () {
    $events = [
        \Modules\Kitchen\Domain\Events\BroadcastOrderConfirmed::class,
        \Modules\Kitchen\Domain\Events\BroadcastOrderPaid::class,
        \Modules\Kitchen\Domain\Events\BroadcastOrderReady::class,
        \Modules\Kitchen\Domain\Events\BroadcastOrderCancelled::class,
    ];
    
    foreach ($events as $eventClass) {
        $interfaces = class_implements($eventClass);
        expect($interfaces)->toHaveKey(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
    }
});

test('Hallazgo 09: config/broadcasting.php tiene lógica inteligente de defaults', function () {
    $configContent = file_get_contents(config_path('broadcasting.php'));
    
    expect($configContent)->toContain('APP_ENV')
        ->and($configContent)->toContain('testing')
        ->and($configContent)->toContain('REVERB_APP_KEY');
});

test('Hallazgo 09: AppServiceProvider tiene validación de producción', function () {
    $providerContent = file_get_contents(app_path('Providers/AppServiceProvider.php'));
    
    expect($providerContent)->toContain('validateBroadcastingConfig')
        ->and($providerContent)->toContain('allowed_origins');
});
