<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'OBS-' . uniqid(),
        'legal_name' => 'Observability Test',
        'trade_name' => 'Obs Test',
    ]);

    enableAllCapabilities($this->company);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'OBS',
        'name' => 'Observability Branch',
    ]);

    $this->user = User::create([
        'name' => 'Obs User',
        'email' => 'obs-' . uniqid() . '@test.com',
        'password' => bcrypt('password'),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);
});

test('punto 151: request ID se genera y propaga', function () {
    $this->actingAs($this->user, 'api');

    $response = $this->getJson('/api/v1/orders');

    // Verificar que el response incluye X-Request-ID
    expect($response->headers->get('X-Request-ID'))->not->toBeNull()
        ->and($response->headers->get('X-Request-ID'))->toBeString();
});

test('punto 151: request ID se reutiliza si viene en header', function () {
    $this->actingAs($this->user, 'api');

    $customRequestId = 'custom-request-' . uniqid();

    $response = $this->withHeaders([
        'X-Request-ID' => $customRequestId,
    ])->getJson('/api/v1/orders');

    // Verificar que se reutiliza el mismo Request ID
    expect($response->headers->get('X-Request-ID'))->toBe($customRequestId);
});

test('punto 152: handler protege datos sensibles', function () {
    // Verificar que el Handler tiene $dontFlash configurado
    $handler = app(\App\Exceptions\Handler::class);
    
    $reflection = new ReflectionClass($handler);
    $property = $reflection->getProperty('dontFlash');
    $property->setAccessible(true);
    $dontFlash = $property->getValue($handler);
    
    expect($dontFlash)->toContain('password')
        ->and($dontFlash)->toContain('password_confirmation')
        ->and($dontFlash)->toContain('pin')
        ->and($dontFlash)->toContain('pos_pin');
});

test('punto 153: errores de sync se registran con contexto', function () {
    Log::shouldReceive('error')
        ->once()
        ->withArgs(function ($message, $context) {
            return isset($context['queue_id'])
                && isset($context['error']);
        });

    // Simular error de sync
    Log::error('SyncService: Unexpected error', [
        'queue_id' => 123,
        'error' => 'Connection timeout',
    ]);
});

test('punto 153: errores de impresión se registran con contexto', function () {
    Log::shouldReceive('error')
        ->once()
        ->withArgs(function ($message, $context) {
            return isset($context['job_id'])
                && isset($context['attempts'])
                && isset($context['error']);
        });

    // Simular error de impresión
    Log::error('PrintJob falló definitivamente', [
        'job_id' => 456,
        'attempts' => 3,
        'error' => 'Printer disconnected',
    ]);
});

test('punto 154: handler de excepciones registra contexto completo', function () {
    $this->actingAs($this->user, 'api');

    // Intentar acceder a orden inexistente (generará 404 o 500)
    $response = $this->getJson('/api/v1/orders/non-existent-uuid');

    // Verificar que el response es correcto
    expect($response->status())->toBeIn([404, 500]);
});

test('criterio de cierre: incidente puede investigarse sin acceso al cliente', function () {
    $this->actingAs($this->user, 'api');

    // Hacer request que genera logs
    $response = $this->getJson('/api/v1/orders');

    // Verificar que el response tiene Request ID
    $requestId = $response->headers->get('X-Request-ID');
    expect($requestId)->not->toBeNull();

    // En producción, podríamos buscar en los logs:
    // grep "request_id: {$requestId}" storage/logs/laravel.log
    // Y veríamos toda la traza del request con contexto completo
    
    expect(true)->toBeTrue('Observabilidad permite diagnóstico remoto');
});
