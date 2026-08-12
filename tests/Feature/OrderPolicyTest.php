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
        'tax_id' => 'POLICY-' . uniqid(),
        'legal_name' => 'Policy Test Company',
        'trade_name' => 'Policy Test Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'POLICY',
        'name' => 'Policy Test Branch',
    ]);

    // Crear usuarios de cada rol
    $this->admin = createPolicyUser('admin');
    $this->manager = createPolicyUser('manager');
    $this->waiter = createPolicyUser('waiter');
    $this->waiter2 = createPolicyUser('waiter');
    $this->kitchen = createPolicyUser('kitchen');
    $this->cashier = createPolicyUser('cashier');
});

function createPolicyUser(string $role): User
{
    return User::create([
        'name' => ucfirst($role) . ' ' . uniqid(),
        'email' => "{$role}-" . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => test()->company->id,
        'branch_id' => test()->branch->id,
        'role' => $role,
    ]);
}

function createPolicyDraftOrder(User $waiter): Order
{
    return Order::create([
        'company_id' => test()->company->id,
        'branch_id' => test()->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'waiter_id' => $waiter->id,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 0,
    ]);
}

function policyApiHeaders(User $user): array
{
    $token = JWTAuth::fromUser($user);
    return [
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
    ];
}

// ============================================
// Ver pedido (GET /api/v1/orders/{uuid})
// ============================================

test('admin puede ver cualquier pedido', function () {
    $order = createPolicyDraftOrder($this->waiter);

    $response = $this->withHeaders(policyApiHeaders($this->admin))
        ->getJson("/api/v1/orders/{$order->uuid}");

    $response->assertOk();
});

test('waiter puede ver SUS propios pedidos', function () {
    $order = createPolicyDraftOrder($this->waiter);

    $response = $this->withHeaders(policyApiHeaders($this->waiter))
        ->getJson("/api/v1/orders/{$order->uuid}");

    $response->assertOk();
});

test('waiter NO puede ver pedidos de otros waiters', function () {
    $order = createPolicyDraftOrder($this->waiter);

    $response = $this->withHeaders(policyApiHeaders($this->waiter2))
        ->getJson("/api/v1/orders/{$order->uuid}");

    $response->assertStatus(403);
});

// ============================================
// Eliminar pedido (DELETE /api/v1/orders/{uuid})
// ============================================

test('admin puede eliminar cualquier pedido draft', function () {
    $order = createPolicyDraftOrder($this->waiter);

    $response = $this->withHeaders(policyApiHeaders($this->admin))
        ->deleteJson("/api/v1/orders/{$order->uuid}");

    $response->assertOk();
});

test('waiter puede eliminar SUS propios pedidos draft', function () {
    $order = createPolicyDraftOrder($this->waiter);

    $response = $this->withHeaders(policyApiHeaders($this->waiter))
        ->deleteJson("/api/v1/orders/{$order->uuid}");

    $response->assertOk();
});

test('waiter NO puede eliminar pedidos de otros waiters', function () {
    $order = createPolicyDraftOrder($this->waiter);

    $response = $this->withHeaders(policyApiHeaders($this->waiter2))
        ->deleteJson("/api/v1/orders/{$order->uuid}");

    $response->assertStatus(403);
});

// ============================================
// Confirmar pedido (POST /api/v1/orders/{uuid}/confirm)
// ============================================

test('admin puede confirmar cualquier pedido', function () {
    $order = createPolicyDraftOrder($this->waiter);

    $response = $this->withHeaders(policyApiHeaders($this->admin))
        ->postJson("/api/v1/orders/{$order->uuid}/confirm");

    $response->assertOk();
});

test('waiter puede confirmar SUS propios pedidos', function () {
    $order = createPolicyDraftOrder($this->waiter);

    $response = $this->withHeaders(policyApiHeaders($this->waiter))
        ->postJson("/api/v1/orders/{$order->uuid}/confirm");

    $response->assertOk();
});

test('waiter NO puede confirmar pedidos de otros waiters', function () {
    $order = createPolicyDraftOrder($this->waiter);

    $response = $this->withHeaders(policyApiHeaders($this->waiter2))
        ->postJson("/api/v1/orders/{$order->uuid}/confirm");

    $response->assertStatus(403);
});

test('kitchen NO puede confirmar pedidos', function () {
    $order = createPolicyDraftOrder($this->waiter);

    $response = $this->withHeaders(policyApiHeaders($this->kitchen))
        ->postJson("/api/v1/orders/{$order->uuid}/confirm");

    $response->assertStatus(403);
});

// ============================================
// Preparar pedido (POST /api/v1/orders/{uuid}/prepare)
// ============================================

test('kitchen puede marcar preparing', function () {
    $order = createPolicyDraftOrder($this->waiter);
    $order->status = OrderStatus::CONFIRMED;
    $order->save();

    $response = $this->withHeaders(policyApiHeaders($this->kitchen))
        ->postJson("/api/v1/orders/{$order->uuid}/prepare");

    $response->assertOk();
});

test('waiter NO puede marcar preparing', function () {
    $order = createPolicyDraftOrder($this->waiter);
    $order->status = OrderStatus::CONFIRMED;
    $order->save();

    $response = $this->withHeaders(policyApiHeaders($this->waiter))
        ->postJson("/api/v1/orders/{$order->uuid}/prepare");

    $response->assertStatus(403);
});

test('cashier NO puede marcar preparing', function () {
    $order = createPolicyDraftOrder($this->waiter);
    $order->status = OrderStatus::CONFIRMED;
    $order->save();

    $response = $this->withHeaders(policyApiHeaders($this->cashier))
        ->postJson("/api/v1/orders/{$order->uuid}/prepare");

    $response->assertStatus(403);
});

// ============================================
// Marcar listo (POST /api/v1/orders/{uuid}/ready)
// ============================================

test('kitchen puede marcar ready', function () {
    $order = createPolicyDraftOrder($this->waiter);
    $order->status = OrderStatus::PREPARING;
    $order->save();

    $response = $this->withHeaders(policyApiHeaders($this->kitchen))
        ->postJson("/api/v1/orders/{$order->uuid}/ready");

    $response->assertOk();
});

test('waiter NO puede marcar ready', function () {
    $order = createPolicyDraftOrder($this->waiter);
    $order->status = OrderStatus::PREPARING;
    $order->save();

    $response = $this->withHeaders(policyApiHeaders($this->waiter))
        ->postJson("/api/v1/orders/{$order->uuid}/ready");

    $response->assertStatus(403);
});

// ============================================
// Servir pedido (POST /api/v1/orders/{uuid}/serve)
// ============================================

test('waiter puede servir SUS propios pedidos', function () {
    $order = createPolicyDraftOrder($this->waiter);
    $order->status = OrderStatus::READY;
    $order->save();

    $response = $this->withHeaders(policyApiHeaders($this->waiter))
        ->postJson("/api/v1/orders/{$order->uuid}/serve");

    $response->assertOk();
});

test('waiter NO puede servir pedidos de otros waiters', function () {
    $order = createPolicyDraftOrder($this->waiter);
    $order->status = OrderStatus::READY;
    $order->save();

    $response = $this->withHeaders(policyApiHeaders($this->waiter2))
        ->postJson("/api/v1/orders/{$order->uuid}/serve");

    $response->assertStatus(403);
});

test('kitchen NO puede servir pedidos', function () {
    $order = createPolicyDraftOrder($this->waiter);
    $order->status = OrderStatus::READY;
    $order->save();

    $response = $this->withHeaders(policyApiHeaders($this->kitchen))
        ->postJson("/api/v1/orders/{$order->uuid}/serve");

    $response->assertStatus(403);
});

// ============================================
// Pagar pedido (POST /api/v1/orders/{uuid}/pay)
// ============================================

test('cashier puede marcar paid', function () {
    $order = createPolicyDraftOrder($this->waiter);
    $order->status = OrderStatus::SERVED;
    $order->save();

    $response = $this->withHeaders(policyApiHeaders($this->cashier))
        ->postJson("/api/v1/orders/{$order->uuid}/pay");

    $response->assertOk();
});

test('waiter NO puede marcar paid', function () {
    $order = createPolicyDraftOrder($this->waiter);
    $order->status = OrderStatus::SERVED;
    $order->save();

    $response = $this->withHeaders(policyApiHeaders($this->waiter))
        ->postJson("/api/v1/orders/{$order->uuid}/pay");

    $response->assertStatus(403);
});

test('kitchen NO puede marcar paid', function () {
    $order = createPolicyDraftOrder($this->waiter);
    $order->status = OrderStatus::SERVED;
    $order->save();

    $response = $this->withHeaders(policyApiHeaders($this->kitchen))
        ->postJson("/api/v1/orders/{$order->uuid}/pay");

    $response->assertStatus(403);
});

// ============================================
// Cerrar pedido (POST /api/v1/orders/{uuid}/close)
// ============================================

test('cashier puede cerrar pedidos', function () {
    $order = createPolicyDraftOrder($this->waiter);
    $order->status = OrderStatus::PAID;
    $order->save();

    $response = $this->withHeaders(policyApiHeaders($this->cashier))
        ->postJson("/api/v1/orders/{$order->uuid}/close");

    $response->assertOk();
});

test('waiter NO puede cerrar pedidos', function () {
    $order = createPolicyDraftOrder($this->waiter);
    $order->status = OrderStatus::PAID;
    $order->save();

    $response = $this->withHeaders(policyApiHeaders($this->waiter))
        ->postJson("/api/v1/orders/{$order->uuid}/close");

    $response->assertStatus(403);
});

// ============================================
// Cancelar pedido (POST /api/v1/orders/{uuid}/cancel)
// ============================================

test('admin puede cancelar cualquier pedido', function () {
    $order = createPolicyDraftOrder($this->waiter);

    $response = $this->withHeaders(policyApiHeaders($this->admin))
        ->postJson("/api/v1/orders/{$order->uuid}/cancel", [
            'reason' => 'Test cancellation',
        ]);

    $response->assertOk();
});

test('waiter puede cancelar SUS propios pedidos', function () {
    $order = createPolicyDraftOrder($this->waiter);

    $response = $this->withHeaders(policyApiHeaders($this->waiter))
        ->postJson("/api/v1/orders/{$order->uuid}/cancel", [
            'reason' => 'Cliente cambió de opinión',
        ]);

    $response->assertOk();
});

test('waiter NO puede cancelar pedidos de otros waiters', function () {
    $order = createPolicyDraftOrder($this->waiter);

    $response = $this->withHeaders(policyApiHeaders($this->waiter2))
        ->postJson("/api/v1/orders/{$order->uuid}/cancel", [
            'reason' => 'Test',
        ]);

    $response->assertStatus(403);
});

test('kitchen NO puede cancelar pedidos', function () {
    $order = createPolicyDraftOrder($this->waiter);

    $response = $this->withHeaders(policyApiHeaders($this->kitchen))
        ->postJson("/api/v1/orders/{$order->uuid}/cancel", [
            'reason' => 'Test',
        ]);

    $response->assertStatus(403);
});
