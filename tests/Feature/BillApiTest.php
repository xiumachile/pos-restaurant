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
    // Tenant A
    $this->company = Company::create([
        'tax_id' => 'BILL-API-' . uniqid(),
        'legal_name' => 'Bill API Company',
        'trade_name' => 'Bill API Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'BILL-API',
        'name' => 'Bill API Branch',
    ]);

    $this->cashier = User::create([
        'name' => 'Test Cashier',
        'email' => 'bill-cashier-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    $this->waiter = User::create([
        'name' => 'Test Waiter',
        'email' => 'bill-waiter-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    // Tenant B
    $this->companyB = Company::create([
        'tax_id' => 'BILL-API-B-' . uniqid(),
        'legal_name' => 'Bill API Company B',
        'trade_name' => 'Bill API Restaurant B',
    ]);

    $this->branchB = Branch::create([
        'company_id' => $this->companyB->id,
        'code' => 'BILL-B',
        'name' => 'Bill API Branch B',
    ]);

    $this->cashierB = User::create([
        'name' => 'Test Cashier B',
        'email' => 'bill-cashier-b-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'role' => 'cashier',
    ]);

    $this->waiterB = User::create([
        'name' => 'Test Waiter B',
        'email' => 'bill-waiter-b-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'role' => 'waiter',
    ]);

    $this->token = JWTAuth::fromUser($this->cashier);
    $this->tokenB = JWTAuth::fromUser($this->cashierB);
});

function billApiHeaders(?string $token = null): array
{
    return [
        'Authorization' => 'Bearer ' . ($token ?? test()->token),
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

function billApiCreateServedOrder($test): Order
{
    return Order::create([
        'company_id' => $test->company->id,
        'branch_id' => $test->branch->id,
        'order_number' => 'BILL-ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::SERVED,
        'waiter_id' => $test->waiter->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
    ]);
}

function billApiCreateServedOrderB($test): Order
{
    return Order::create([
        'company_id' => $test->companyB->id,
        'branch_id' => $test->branchB->id,
        'order_number' => 'BILL-ORD-B-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::SERVED,
        'waiter_id' => $test->waiterB->id,
        'subtotal' => 5000,
        'tax_amount' => 950,
        'discount_amount' => 0,
        'total' => 5950,
    ]);
}

// ============================================
// POST /api/v1/orders/{uuid}/split - Split Bill
// ============================================

test('POST /api/v1/orders/{uuid}/split por partes iguales', function () {
    $order = billApiCreateServedOrder($this);

    $response = $this->withHeaders(billApiHeaders())
        ->postJson("/api/v1/orders/{$order->uuid}/split", [
            'type' => 'equal_split',
            'parts' => 2,
        ]);

    $response->assertOk()
        ->assertJsonCount(2, 'data');

    $total = 0;
    foreach ($response->json('data') as $bill) {
        $total += $bill['total'];
    }
    expect(round($total, 2))->toBe(11900.0);
});

test('POST /api/v1/orders/{uuid}/split por partes iguales con residuos', function () {
    $order = billApiCreateServedOrder($this);

    $response = $this->withHeaders(billApiHeaders())
        ->postJson("/api/v1/orders/{$order->uuid}/split", [
            'type' => 'equal_split',
            'parts' => 3,
        ]);

    $response->assertOk()
        ->assertJsonCount(3, 'data');

    $total = 0;
    foreach ($response->json('data') as $bill) {
        $total += $bill['total'];
    }
    expect(round($total, 2))->toBe(11900.0);
});

test('POST /api/v1/orders/{uuid}/split por montos personalizados', function () {
    $order = billApiCreateServedOrder($this);

    $response = $this->withHeaders(billApiHeaders())
        ->postJson("/api/v1/orders/{$order->uuid}/split", [
            'type' => 'custom_amount',
            'amounts' => [5000, 6900],
        ]);

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.total', 5000)
        ->assertJsonPath('data.1.total', 6900);
});

test('POST /api/v1/orders/{uuid}/split deniega montos que exceden total', function () {
    $order = billApiCreateServedOrder($this);

    $response = $this->withHeaders(billApiHeaders())
        ->postJson("/api/v1/orders/{$order->uuid}/split", [
            'type' => 'custom_amount',
            'amounts' => [99999, 50000],
        ]);

    $response->assertStatus(422);
});

test('POST /api/v1/orders/{uuid}/split deniega tipo invalido', function () {
    $order = billApiCreateServedOrder($this);

    $response = $this->withHeaders(billApiHeaders())
        ->postJson("/api/v1/orders/{$order->uuid}/split", [
            'type' => 'invalid_type',
        ]);

    $response->assertStatus(422);
});

// ============================================
// GET /api/v1/orders/{uuid}/bills
// ============================================

test('GET /api/v1/orders/{uuid}/bills retorna sub-cuentas', function () {
    $order = billApiCreateServedOrder($this);

    $this->withHeaders(billApiHeaders())
        ->postJson("/api/v1/orders/{$order->uuid}/split", [
            'type' => 'equal_split',
            'parts' => 2,
        ]);

    $response = $this->withHeaders(billApiHeaders())
        ->getJson("/api/v1/orders/{$order->uuid}/bills");

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

// ============================================
// Cross-tenant isolation
// ============================================

test('POST /api/v1/orders/{uuid}/split usuario B no puede dividir orden de empresa A', function () {
    $orderA = billApiCreateServedOrder($this);

    $response = $this->withHeaders(billApiHeaders($this->tokenB))
        ->postJson("/api/v1/orders/{$orderA->uuid}/split", [
            'type' => 'equal_split',
            'parts' => 2,
        ]);

    expect($response->status())->toBeIn([403, 404, 422]);
});

test('GET /api/v1/orders/{uuid}/bills usuario B no puede ver bills de empresa A', function () {
    $orderA = billApiCreateServedOrder($this);

    // Primer request: split con usuario A
    $this->withHeaders(billApiHeaders())
        ->postJson("/api/v1/orders/{$orderA->uuid}/split", [
            'type' => 'equal_split',
            'parts' => 2,
        ]);

    // Limpiar estado entre requests (guard JWT cachea usuario del request anterior)
    switchJwtUser();

    // Segundo request: index con usuario B
    $response = $this->withHeaders(billApiHeaders($this->tokenB))
        ->getJson("/api/v1/orders/{$orderA->uuid}/bills");

    expect($response->status())->toBeIn([403, 404]);
});

test('sin autenticacion retorna 401', function () {
    $order = billApiCreateServedOrder($this);

    $response = $this->postJson("/api/v1/orders/{$order->uuid}/split", [
        'type' => 'equal_split',
        'parts' => 2,
    ]);

    $response->assertStatus(401);
});
