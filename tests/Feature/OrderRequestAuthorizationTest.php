<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Tables\Domain\Entities\RestaurantTable;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use App\Shared\Application\TenantContext;

uses(RefreshDatabase::class);

/**
 * Tests de autorización de FormRequests (Hallazgo 11).
 */

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'REQ-AUTH-' . uniqid(),
        'legal_name' => 'Request Auth Test Company',
        'trade_name' => 'Req Auth Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'REQ-AUTH',
        'name' => 'Request Auth Branch',
    ]);

    $this->table = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón Principal', 'zh' => '主厅'],
        'table_number' => '1',
        'capacity' => 4,
    ]);

    $this->admin = createRequestAuthUser('admin');
    $this->manager = createRequestAuthUser('manager');
    $this->waiter = createRequestAuthUser('waiter');
    $this->cashier = createRequestAuthUser('cashier');
    $this->kitchen = createRequestAuthUser('kitchen');
});

function createRequestAuthUser(string $role): User
{
    return User::create([
        'name' => ucfirst($role) . ' ' . uniqid(),
        'email' => "{$role}-" . uniqid() . '@reqauth.test',
        'password' => bcrypt('password'),
        'company_id' => test()->company->id,
        'branch_id' => test()->branch->id,
        'role' => $role,
    ]);
}

function requestAuthHeaders(User $user): array
{
    $token = JWTAuth::fromUser($user);
    return [
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

function createRequestAuthOrder(User $waiter): Order
{
    $tenantContext = app(TenantContext::class);
    $tenantContext->setCompany(test()->company->id, test()->branch->id, $waiter->id, null, $waiter->role);
    
    return Order::create([
        'company_id' => test()->company->id,
        'branch_id' => test()->branch->id,
        'waiter_id' => $waiter->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 0,
    ]);
}

// ============================================
// CreateOrderRequest - Gate::allows('create', Order::class)
// ============================================

test('Hallazgo 11: waiter puede crear pedidos', function () {
    $response = $this->withHeaders(requestAuthHeaders($this->waiter))
        ->postJson('/api/v1/orders', [
            'type' => 'dine_in',
            'table_uuid' => $this->table->uuid,
        ]);

    $response->assertStatus(201);
});

test('Hallazgo 11: cashier puede crear pedidos', function () {
    $response = $this->withHeaders(requestAuthHeaders($this->cashier))
        ->postJson('/api/v1/orders', [
            'type' => 'takeout',
        ]);

    $response->assertStatus(201);
});

test('Hallazgo 11: manager puede crear pedidos', function () {
    $response = $this->withHeaders(requestAuthHeaders($this->manager))
        ->postJson('/api/v1/orders', [
            'type' => 'dine_in',
            'table_uuid' => $this->table->uuid,
        ]);

    $response->assertStatus(201);
});

test('Hallazgo 11: admin puede crear pedidos', function () {
    $response = $this->withHeaders(requestAuthHeaders($this->admin))
        ->postJson('/api/v1/orders', [
            'type' => 'dine_in',
            'table_uuid' => $this->table->uuid,
        ]);

    $response->assertStatus(201);
});

test('Hallazgo 11: kitchen NO puede crear pedidos', function () {
    $response = $this->withHeaders(requestAuthHeaders($this->kitchen))
        ->postJson('/api/v1/orders', [
            'type' => 'dine_in',
            'table_uuid' => $this->table->uuid,
        ]);

    $response->assertStatus(403);
});

// ============================================
// UpdateOrderRequest - Gate::allows('update', $order)
// ============================================

test('Hallazgo 11: waiter puede actualizar SUS propios pedidos', function () {
    $order = createRequestAuthOrder($this->waiter);

    $response = $this->withHeaders(requestAuthHeaders($this->waiter))
        ->putJson("/api/v1/orders/{$order->uuid}", [
            'notes' => 'Updated by waiter',
        ]);

    $response->assertOk();
});

test('Hallazgo 11: waiter NO puede actualizar pedidos de otros waiters', function () {
    $otherWaiter = createRequestAuthUser('waiter');
    $order = createRequestAuthOrder($otherWaiter);

    $response = $this->withHeaders(requestAuthHeaders($this->waiter))
        ->putJson("/api/v1/orders/{$order->uuid}", [
            'notes' => 'Should not work',
        ]);

    $response->assertStatus(403);
});

test('Hallazgo 11: manager puede actualizar cualquier pedido', function () {
    $order = createRequestAuthOrder($this->waiter);

    $response = $this->withHeaders(requestAuthHeaders($this->manager))
        ->putJson("/api/v1/orders/{$order->uuid}", [
            'notes' => 'Updated by manager',
        ]);

    $response->assertOk();
});

// ============================================
// CancelOrderRequest - Gate::allows('cancel', $order)
// ============================================

test('Hallazgo 11: waiter puede cancelar SUS propios pedidos draft', function () {
    $order = createRequestAuthOrder($this->waiter);

    $response = $this->withHeaders(requestAuthHeaders($this->waiter))
        ->postJson("/api/v1/orders/{$order->uuid}/cancel", [
            'reason' => 'Test',
        ]);

    // Puede ser 200 (cancelado) o 422 (no se puede cancelar por estado)
    // Lo importante es que NO sea 403 (forbidden)
    expect($response->status())->not->toBe(403);
});

test('Hallazgo 11: kitchen NO puede cancelar pedidos', function () {
    $order = createRequestAuthOrder($this->waiter);

    $response = $this->withHeaders(requestAuthHeaders($this->kitchen))
        ->postJson("/api/v1/orders/{$order->uuid}/cancel", [
            'reason' => 'Test',
        ]);

    $response->assertStatus(403);
});
