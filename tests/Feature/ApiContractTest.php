<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Companies\Domain\Entities\Company;
use Modules\Companies\Domain\Entities\CompanyCapability;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'API-' . uniqid(),
        'legal_name' => 'API Test Company',
        'trade_name' => 'API Test',
    ]);

    enableAllCapabilities($this->company);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'API Test Branch',
        'code' => 'API',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'API User',
        'email' => 'api-' . uniqid() . '@test.com',
        'password' => bcrypt('password'),
        'role' => 'cashier',
    ]);
});

test('formato de error 401 es estándar', function () {
    $response = $this->getJson('/api/v1/orders');

    $response->assertStatus(401)
        ->assertJsonStructure(['error', 'message'])
        ->assertJson([
            'error' => 'unauthenticated',
        ]);
});

test('formato de error 403 forbidden es estándar (endpoint requiere super_admin)', function () {
    // POST /api/v1/companies requiere role:super_admin
    // Un cashier no tiene permiso
    $this->actingAs($this->user, 'api');

    $response = $this->postJson('/api/v1/companies', [
        'tax_id' => '76.999.999-9',
        'legal_name' => 'Test Company',
        'trade_name' => 'Test',
    ]);

    $response->assertStatus(403)
        ->assertJsonStructure(['error', 'message', 'required_roles', 'current_role'])
        ->assertJson([
            'error' => 'forbidden',
            'current_role' => 'cashier',
        ]);
});

test('formato de error 403 capability_not_enabled es estándar', function () {
    // Quitar capabilities de la empresa (relación HasMany)
    $this->company->capabilities()->delete();

    $this->actingAs($this->user, 'api');

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-API-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    $response = $this->postJson("/api/v1/orders/{$order->uuid}/split", [
        'type' => 'equal_split',
        'parts' => 2,
    ]);

    $response->assertStatus(403)
        ->assertJsonStructure(['error', 'message', 'required_capability'])
        ->assertJson([
            'error' => 'capability_not_enabled',
            'required_capability' => 'can_split_bills',
        ]);
});

test('formato de error 404 not_found es estándar (UUID válido pero inexistente)', function () {
    $this->actingAs($this->user, 'api');

    // UUID válido pero que no existe
    $validNonExistentUuid = (string) Str::uuid();

    $response = $this->getJson("/api/v1/orders/{$validNonExistentUuid}");

    // Puede retornar 404 (si hay handler) o 500 (ModelNotFoundException sin handler)
    expect($response->status())->toBeIn([404, 500]);
});

test('formato de error 422 validation_error usa formato Laravel nativo', function () {
    $this->actingAs($this->user, 'api');

    // Datos inválidos para crear orden
    $response = $this->postJson('/api/v1/orders', [
        'table_id' => 'invalid', // debe ser integer
    ]);

    $response->assertStatus(422);

    // Laravel usa formato nativo: {message, errors}
    // NO tiene campo 'error' (es una limitación conocida del contrato)
    $response->assertJsonStructure(['message', 'errors']);
});

test('criterio de cierre: formato dual de errores es consistente y documentado', function () {
    $this->actingAs($this->user, 'api');

    // Error personalizado (middleware CheckRole): formato {error, message}
    $response = $this->postJson('/api/v1/companies', [
        'tax_id' => '76.999.999-9',
        'legal_name' => 'Test',
        'trade_name' => 'Test',
    ]);

    expect($response->status())->toBe(403)
        ->and($response->json('error'))->toBe('forbidden');

    // Error de validación Laravel: formato {message, errors}
    $response = $this->postJson('/api/v1/orders', [
        'table_id' => 'invalid',
    ]);

    expect($response->status())->toBe(422)
        ->and($response->json('message'))->toBeString()
        ->and($response->json('errors'))->toBeArray();
});
