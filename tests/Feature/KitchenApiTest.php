<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'KITCHEN-' . uniqid(),
        'legal_name' => 'Kitchen Test Company',
        'trade_name' => 'Kitchen Test Restaurant',
    ]);

    enableAllCapabilities($this->company);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'KITCHEN',
        'name' => 'Kitchen Test Branch',
    ]);

    $this->kitchenUser = User::create([
        'name' => 'Kitchen User',
        'email' => 'kitchen-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'kitchen',
    ]);

    $this->token = JWTAuth::fromUser($this->kitchenUser);
});

function kitchenHeaders(): array
{
    return [
        'Authorization' => "Bearer " . test()->token,
        'Accept' => 'application/json',
    ];
}

function createKitchenOrder($status, $test): Order
{
    return Order::create([
        'company_id' => $test->company->id,
        'branch_id' => $test->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => $status,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
        'confirmed_at' => now()->subMinutes(5),
    ]);
}

// ============================================
// GET /api/v1/kitchen/queue
// ============================================

test('GET /api/v1/kitchen/queue retorna pedidos en cola', function () {
    createKitchenOrder(OrderStatus::CONFIRMED, $this);
    createKitchenOrder(OrderStatus::PREPARING, $this);
    createKitchenOrder(OrderStatus::READY, $this); // No debería aparecer

    $response = $this->withHeaders(kitchenHeaders())
        ->getJson('/api/v1/kitchen/queue');

    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toBeArray();

    // Sumar todos los pedidos de todas las zonas
    $totalOrders = collect($data)->sum('count');
    expect($totalOrders)->toBe(2); // Solo confirmed + preparing
});

test('GET /api/v1/kitchen/queue retorna estructura por zona', function () {
    createKitchenOrder(OrderStatus::CONFIRMED, $this);

    $response = $this->withHeaders(kitchenHeaders())
        ->getJson('/api/v1/kitchen/queue');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['zone', 'orders', 'count'],
            ],
        ]);
});

// ============================================
// GET /api/v1/kitchen/stats
// ============================================

test('GET /api/v1/kitchen/stats retorna estadisticas correctas', function () {
    createKitchenOrder(OrderStatus::CONFIRMED, $this);
    createKitchenOrder(OrderStatus::CONFIRMED, $this);
    createKitchenOrder(OrderStatus::PREPARING, $this);
    createKitchenOrder(OrderStatus::READY, $this);

    $response = $this->withHeaders(kitchenHeaders())
        ->getJson('/api/v1/kitchen/stats');

    $response->assertOk()
        ->assertJsonPath('data.confirmed', 2)
        ->assertJsonPath('data.preparing', 1)
        ->assertJsonPath('data.ready', 1)
        ->assertJsonPath('data.total_active', 4)
        ->assertJsonStructure([
            'data' => [
                'confirmed',
                'preparing',
                'ready',
                'total_active',
                'avg_preparation_minutes',
                'orders_last_hour',
            ],
        ]);
});

// ============================================
// GET /api/v1/kitchen/history
// ============================================

test('GET /api/v1/kitchen/history retorna pedidos completados', function () {
    createKitchenOrder(OrderStatus::SERVED, $this);
    createKitchenOrder(OrderStatus::PAID, $this);
    createKitchenOrder(OrderStatus::CLOSED, $this);
    createKitchenOrder(OrderStatus::CONFIRMED, $this); // No debería aparecer

    $response = $this->withHeaders(kitchenHeaders())
        ->getJson('/api/v1/kitchen/history');

    $response->assertOk();

    $data = $response->json('data');
    expect(count($data))->toBe(3); // Solo served, paid, closed
});

// ============================================
// Aislamiento por sucursal
// ============================================

test('kitchen solo ve pedidos de su sucursal', function () {
    // Crear otra sucursal
    $otherBranch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'OTHER',
        'name' => 'Other Branch',
    ]);

    // Pedido de la sucursal actual
    createKitchenOrder(OrderStatus::CONFIRMED, $this);

    // Pedido de otra sucursal
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $otherBranch->id,
        'order_number' => 'ORD-OTHER-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
        'confirmed_at' => now(),
    ]);

    $response = $this->withHeaders(kitchenHeaders())
        ->getJson('/api/v1/kitchen/queue');

    $response->assertOk();

    $data = $response->json('data');
    $totalOrders = collect($data)->sum('count');
    expect($totalOrders)->toBe(1); // Solo el de su sucursal
});

// ============================================
// Sin autenticación
// ============================================

test('sin autenticacion retorna 401', function () {
    $response = $this->getJson('/api/v1/kitchen/queue');
    $response->assertStatus(401);
});
