<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderPriority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'ASSIGN-' . uniqid(),
        'legal_name' => 'Assignment Test Company',
        'trade_name' => 'Assignment Test Restaurant',
    ]);

    enableAllCapabilities($this->company);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'ASSIGN',
        'name' => 'Assignment Test Branch',
    ]);

    $this->kitchenUser = User::create([
        'name' => 'Kitchen User',
        'email' => 'kitchen-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'kitchen',
    ]);

    $this->cookUser = User::create([
        'name' => 'Cook User',
        'email' => 'cook-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'kitchen',
    ]);

    $this->token = JWTAuth::fromUser($this->kitchenUser);
});

function assignHeaders(): array
{
    return [
        'Authorization' => "Bearer " . test()->token,
        'Accept' => 'application/json',
    ];
}

function createAssignmentOrder(OrderStatus $status): Order
{
    return Order::create([
        'company_id' => test()->company->id,
        'branch_id' => test()->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => $status,
        'priority' => OrderPriority::NORMAL,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
        'confirmed_at' => now()->subMinutes(5),
    ]);
}

// ============================================
// POST /api/v1/kitchen/orders/{uuid}/assign-cook
// ============================================

test('kitchen puede asignar cocinero a pedido en cola', function () {
    $order = createAssignmentOrder(OrderStatus::CONFIRMED);

    $response = $this->withHeaders(assignHeaders())
        ->postJson("/api/v1/kitchen/orders/{$order->uuid}/assign-cook", [
            'cook_uuid' => $this->cookUser->uuid,
        ]);

    $response->assertOk();

    $order->refresh();
    expect($order->assigned_cook_id)->toBe($this->cookUser->id);
});

test('deniega asignar cocinero a pedido fuera de cola de cocina', function () {
    $order = createAssignmentOrder(OrderStatus::SERVED);

    $response = $this->withHeaders(assignHeaders())
        ->postJson("/api/v1/kitchen/orders/{$order->uuid}/assign-cook", [
            'cook_uuid' => $this->cookUser->uuid,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'invalid_state');
});

test('deniega asignar cocinero inexistente', function () {
    $order = createAssignmentOrder(OrderStatus::CONFIRMED);

    $response = $this->withHeaders(assignHeaders())
        ->postJson("/api/v1/kitchen/orders/{$order->uuid}/assign-cook", [
            'cook_uuid' => '00000000-0000-0000-0000-000000000000',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.cook_uuid.0', 'El cocinero especificado no existe.');
});

// ============================================
// POST /api/v1/kitchen/orders/{uuid}/priority
// ============================================

test('kitchen puede cambiar prioridad de pedido activo', function () {
    $order = createAssignmentOrder(OrderStatus::CONFIRMED);
    expect($order->priority)->toBe(OrderPriority::NORMAL);

    $response = $this->withHeaders(assignHeaders())
        ->postJson("/api/v1/kitchen/orders/{$order->uuid}/priority", [
            'priority' => 'rush',
        ]);

    $response->assertOk();

    $order->refresh();
    expect($order->priority)->toBe(OrderPriority::RUSH);
});

test('kitchen puede cambiar prioridad a VIP', function () {
    $order = createAssignmentOrder(OrderStatus::PREPARING);

    $response = $this->withHeaders(assignHeaders())
        ->postJson("/api/v1/kitchen/orders/{$order->uuid}/priority", [
            'priority' => 'vip',
        ]);

    $response->assertOk();

    $order->refresh();
    expect($order->priority)->toBe(OrderPriority::VIP);
});

test('deniega cambiar prioridad a pedido en estado final', function () {
    $order = createAssignmentOrder(OrderStatus::CLOSED);

    $response = $this->withHeaders(assignHeaders())
        ->postJson("/api/v1/kitchen/orders/{$order->uuid}/priority", [
            'priority' => 'rush',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'invalid_state');
});

test('deniega prioridad invalida', function () {
    $order = createAssignmentOrder(OrderStatus::CONFIRMED);

    $response = $this->withHeaders(assignHeaders())
        ->postJson("/api/v1/kitchen/orders/{$order->uuid}/priority", [
            'priority' => 'invalid',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.priority.0', 'La prioridad debe ser: normal, rush o vip.');
});

// ============================================
// Ordenamiento por prioridad en cola
// ============================================

test('cola de cocina ordena por prioridad (VIP primero)', function () {
    // Crear pedidos con diferentes prioridades
    $normalOrder = createAssignmentOrder(OrderStatus::CONFIRMED);
    $normalOrder->priority = OrderPriority::NORMAL;
    $normalOrder->save();

    $rushOrder = createAssignmentOrder(OrderStatus::CONFIRMED);
    $rushOrder->priority = OrderPriority::RUSH;
    $rushOrder->save();

    $vipOrder = createAssignmentOrder(OrderStatus::CONFIRMED);
    $vipOrder->priority = OrderPriority::VIP;
    $vipOrder->save();

    $response = $this->withHeaders(assignHeaders())
        ->getJson('/api/v1/kitchen/queue');

    $response->assertOk();

    $data = $response->json('data');
    
    // Verificar que hay pedidos
    expect($data)->toBeArray();
    $totalOrders = collect($data)->sum('count');
    expect($totalOrders)->toBe(3);

    // Verificar orden: VIP debería aparecer antes que RUSH, y RUSH antes que NORMAL
    $allOrders = collect($data)->pluck('orders')->flatten(1);
    $priorities = $allOrders->pluck('priority')->toArray();
    
    // VIP debe estar antes que RUSH
    $vipIndex = array_search('vip', $priorities);
    $rushIndex = array_search('rush', $priorities);
    $normalIndex = array_search('normal', $priorities);
    
    expect($vipIndex)->toBeLessThan($rushIndex);
    expect($rushIndex)->toBeLessThan($normalIndex);
});

// ============================================
// Sin autenticación
// ============================================

test('sin autenticacion retorna 401', function () {
    $order = createAssignmentOrder(OrderStatus::CONFIRMED);
    
    $response = $this->postJson("/api/v1/kitchen/orders/{$order->uuid}/assign-cook", [
        'cook_uuid' => $this->cookUser->uuid,
    ]);
    
    $response->assertStatus(401);
});
