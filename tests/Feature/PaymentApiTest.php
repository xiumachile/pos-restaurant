<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\ValueObjects\BillType;
use Modules\Payments\Domain\ValueObjects\BillStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'PAY-API-' . uniqid(),
        'legal_name' => 'Payment API Company',
        'trade_name' => 'Payment API Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'PAY-API',
        'name' => 'Payment API Branch',
    ]);

    $this->cashier = User::create([
        'name' => 'Test Cashier',
        'email' => 'cashier-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    $this->waiter = User::create([
        'name' => 'Test Waiter',
        'email' => 'waiter-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    // Crear método de pago efectivo
    $this->cashMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'cash',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    // Crear método de pago tarjeta
    $this->cardMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'card',
        'name_translations' => ['es' => 'Tarjeta'],
        'type' => 'card',
        'requires_reference' => true,
        'is_active' => true,
    ]);

    $this->token = JWTAuth::fromUser($this->cashier);
});

function payHeaders(): array
{
    return [
        'Authorization' => "Bearer " . test()->token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

function createServedOrder($test): Order
{
    return Order::create([
        'company_id' => $test->company->id,
        'branch_id' => $test->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::SERVED,
        'waiter_id' => $test->waiter->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
    ]);
}

// ============================================
// POST /api/v1/payments - Registro de pago
// ============================================

test('POST /api/v1/billing/payments registra pago completo en efectivo', function () {
    $this->withoutExceptionHandling(); // Mostrar la excepcion real
    $order = createServedOrder($this);

    $response = $this->withHeaders(payHeaders())
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $order->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 11900,
            'tip_amount' => 1190,
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.method_code', 'cash')
        ->assertJsonPath('data.amount', 11900)
        ->assertJsonPath('data.tip_amount', 1190)
        ->assertJsonPath('data.total_amount', 13090)
        ->assertJsonPath('data.status', 'completed');
});

test('POST /api/v1/billing/payments es idempotente con misma key', function () {
    $order = createServedOrder($this);
    $idempotencyKey = Str::uuid()->toString();

    // Primera petición
    $response1 = $this->withHeaders(payHeaders())
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $order->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 11900,
            'idempotency_key' => $idempotencyKey,
        ]);

    $response1->assertStatus(201);
    $paymentUuid1 = $response1->json('data.uuid');

    // Segunda petición con misma key
    $response2 = $this->withHeaders(payHeaders())
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $order->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 11900,
            'idempotency_key' => $idempotencyKey,
        ]);

    // Retorna 201 (no duplica) con el mismo pago
    $response2->assertStatus(201);
    $paymentUuid2 = $response2->json('data.uuid');

    expect($paymentUuid1)->toBe($paymentUuid2);
});

test('POST /api/v1/billing/payments requiere idempotency_key', function () {
    $order = createServedOrder($this);

    $response = $this->withHeaders(payHeaders())
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $order->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 11900,
        ]);

    $response->assertStatus(422);
});

test('POST /api/v1/billing/payments deniega pago mayor al total', function () {
    $order = createServedOrder($this);

    $response = $this->withHeaders(payHeaders())
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $order->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 99999,
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    $response->assertStatus(422);
});

test('POST /api/v1/billing/payments con tarjeta requiere referencia', function () {
    $order = createServedOrder($this);

    $response = $this->withHeaders(payHeaders())
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $order->uuid,
            'payment_method_uuid' => $this->cardMethod->uuid,
            'amount' => 11900,
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    $response->assertStatus(422);
});

// ============================================
// POST /api/v1/orders/{uuid}/split - Split Bill
// ============================================

test('POST /api/v1/orders/{uuid}/split por partes iguales', function () {
    $order = createServedOrder($this);

    $response = $this->withHeaders(payHeaders())
        ->postJson("/api/v1/orders/{$order->uuid}/split", [
            'type' => 'equal_split',
            'parts' => 2,
        ]);

    $response->assertOk()
        ->assertJsonCount(2, 'data');

    // Verificar que la suma de las dos bills sea el total
    $total = 0;
    foreach ($response->json('data') as $bill) {
        $total += $bill['total'];
    }
    expect(round($total, 2))->toBe(11900.0);
});

test('POST /api/v1/orders/{uuid}/split por partes iguales con residuos', function () {
    $order = createServedOrder($this);

    // Split en 3 partes (11900 / 3 = 3966.67 con residuo)
    $response = $this->withHeaders(payHeaders())
        ->postJson("/api/v1/orders/{$order->uuid}/split", [
            'type' => 'equal_split',
            'parts' => 3,
        ]);

    $response->assertOk()
        ->assertJsonCount(3, 'data');

    // Verificar que la suma exacta sea el total del pedido
    $total = 0;
    foreach ($response->json('data') as $bill) {
        $total += $bill['total'];
    }
    expect(round($total, 2))->toBe(11900.0);
});

test('POST /api/v1/orders/{uuid}/split por montos personalizados', function () {
    $order = createServedOrder($this);

    $response = $this->withHeaders(payHeaders())
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
    $order = createServedOrder($this);

    $response = $this->withHeaders(payHeaders())
        ->postJson("/api/v1/orders/{$order->uuid}/split", [
            'type' => 'custom_amount',
            'amounts' => [99999, 50000],
        ]);

    $response->assertStatus(422);
});

test('POST /api/v1/orders/{uuid}/split deniega tipo invalido', function () {
    $order = createServedOrder($this);

    $response = $this->withHeaders(payHeaders())
        ->postJson("/api/v1/orders/{$order->uuid}/split", [
            'type' => 'invalid_type',
        ]);

    $response->assertStatus(422);
});

// ============================================
// GET /api/v1/orders/{uuid}/bills
// ============================================

test('GET /api/v1/orders/{uuid}/bills retorna sub-cuentas', function () {
    $order = createServedOrder($this);

    // Generar split primero
    $this->withHeaders(payHeaders())
        ->postJson("/api/v1/orders/{$order->uuid}/split", [
            'type' => 'equal_split',
            'parts' => 2,
        ]);

    $response = $this->withHeaders(payHeaders())
        ->getJson("/api/v1/orders/{$order->uuid}/bills");

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

// ============================================
// Cash Sessions
// ============================================

test('POST /api/v1/cash-sessions/open abre sesion de caja', function () {
    $response = $this->withHeaders(payHeaders())
        ->postJson('/api/v1/cash-sessions/open', [
            'opening_amount' => 50000,
            'notes' => 'Apertura turno mañana',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.opening_amount', 50000);
});

test('POST /api/v1/cash-sessions/open deniega si ya hay sesion abierta', function () {
    // Abrir primera sesión
    $this->withHeaders(payHeaders())
        ->postJson('/api/v1/cash-sessions/open', [
            'opening_amount' => 50000,
        ]);

    // Intentar abrir segunda
    $response = $this->withHeaders(payHeaders())
        ->postJson('/api/v1/cash-sessions/open', [
            'opening_amount' => 30000,
        ]);

    $response->assertStatus(422);
});

test('GET /api/v1/cash-sessions/current retorna sesion abierta', function () {
    $this->withHeaders(payHeaders())
        ->postJson('/api/v1/cash-sessions/open', [
            'opening_amount' => 50000,
        ]);

    $response = $this->withHeaders(payHeaders())
        ->getJson('/api/v1/cash-sessions/current');

    $response->assertOk()
        ->assertJsonPath('data.status', 'open');
});

test('sin autenticacion retorna 401', function () {
    $response = $this->getJson('/api/v1/cash-sessions/current');
    $response->assertStatus(401);
});
