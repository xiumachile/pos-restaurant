<?php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Companies\Domain\Entities\Company;
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
test('formato de error 403 forbidden es estándar', function () {
$admin = User::create([
'company_id' => $this->company->id,
'branch_id' => $this->branch->id,
'name' => 'Admin',
'email' => 'admin-' . uniqid() . '@test.com',
'password' => bcrypt('password'),
'role' => 'admin',
]);
$waiter = User::create([
    'company_id' => $this->company->id,
    'branch_id' => $this->branch->id,
    'name' => 'Waiter',
    'email' => 'waiter-' . uniqid() . '@test.com',
    'password' => bcrypt('password'),
    'role' => 'waiter',
]);

$this->actingAs($waiter, 'api');

// Intentar acceder a endpoint que requiere admin
$response = $this->getJson('/api/v1/companies');

$response->assertStatus(403)
    ->assertJsonStructure(['error', 'message', 'required_roles', 'current_role'])
    ->assertJson([
        'error' => 'forbidden',
        'current_role' => 'waiter',
    ]);
});
test('formato de error 403 capability_not_enabled es estándar', function () {
// Deshabilitar capability
$this->company->capabilities()->detach();
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
test('formato de error 404 not_found es estándar', function () {
$this->actingAs($this->user, 'api');
$response = $this->getJson('/api/v1/orders/non-existent-uuid');

$response->assertStatus(404)
    ->assertJsonStructure(['error', 'message'])
    ->assertJson([
        'error' => 'not_found',
    ]);
});
test('formato de error 422 validation_error es estándar', function () {
$this->actingAs($this->user, 'api');
$response = $this->postJson('/api/v1/orders', [
    // Datos inválidos
    'table_id' => 'invalid',
]);

$response->assertStatus(422)
    ->assertJsonStructure(['error', 'message', 'details']);
});
test('criterio de cierre: frontend puede depender de códigos de error', function () {
// Validar que todos los errores tienen campo 'error' (código máquina)
$this->actingAs($this->user, 'api');
// 404
$response = $this->getJson('/api/v1/orders/non-existent');
expect($response->json('error'))->toBeString();

// 422
$response = $this->postJson('/api/v1/orders', []);
expect($response->json('error'))->toBeString();
});
