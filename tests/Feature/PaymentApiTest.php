<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Domain\Entities\Account;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Tenant A
    $this->company = Company::create([
        'tax_id' => 'PAY-API-' . uniqid(),
        'legal_name' => 'Payment API Company',
        'trade_name' => 'Payment API Restaurant',
    ]);

    enableAllCapabilities($this->company);

    // Crear cuentas contables por defecto (necesarias para P0-05 Ledger integration)
    Account::seedDefaultsFor($this->company->id);

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

    $this->cashMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'cash',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    $this->cardMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'card',
        'name_translations' => ['es' => 'Tarjeta'],
        'type' => 'card',
        'requires_reference' => true,
        'is_active' => true,
    ]);

    // Tenant B para cross-tenant
    $this->companyB = Company::create([
        'tax_id' => 'PAY-API-B-' . uniqid(),
        'legal_name' => 'Payment API Company B',
        'trade_name' => 'Payment API Restaurant B',
    ]);

    enableAllCapabilities($this->companyB);

    // Crear cuentas contables por defecto para tenant B
    Account::seedDefaultsFor($this->companyB->id);

    $this->branchB = Branch::create([
        'company_id' => $this->companyB->id,
        'code' => 'PAY-B',
        'name' => 'Payment API Branch B',
    ]);

    $this->cashierB = User::create([
        'name' => 'Test Cashier B',
        'email' => 'cashier-b-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'role' => 'cashier',
    ]);

    $this->waiterB = User::create([
        'name' => 'Test Waiter B',
        'email' => 'waiter-b-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'role' => 'waiter',
    ]);

    $this->cashMethodB = PaymentMethod::create([
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'code' => 'cash-b',
        'name_translations' => ['es' => 'Efectivo B'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    $this->token = JWTAuth::fromUser($this->cashier);
    $this->tokenB = JWTAuth::fromUser($this->cashierB);

    // Como enableAllCapabilities() activa requires_cashier_session=ON,
    // debemos abrir una sesión de caja en cada tenant para que los pagos
    // sean aceptados (nueva semántica de la capability).
    CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'session_number' => 'CS-A-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    CashSession::create([
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'user_id' => $this->cashierB->id,
        'session_number' => 'CS-B-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);
});

function paymentApiHeaders(?string $token = null): array
{
    return [
        'Authorization' => 'Bearer ' . ($token ?? test()->token),
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

function paymentApiCreateServedOrder($test): Order
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

function paymentApiCreateServedOrderB($test): Order
{
    return Order::create([
        'company_id' => $test->companyB->id,
        'branch_id' => $test->branchB->id,
        'order_number' => 'ORD-B-' . uniqid(),
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
// POST /api/v1/billing/payments - Registro de pago
// ============================================

test('POST /api/v1/billing/payments registra pago completo en efectivo', function () {
    $order = paymentApiCreateServedOrder($this);

    $response = $this->withHeaders(paymentApiHeaders())
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $order->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 11900,
            'tip_amount' => 1190,
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    if ($response->status() !== 201) {
        dump('=== ERROR 422 ===');
        dump('Status:', $response->status());
        dump('Body:', $response->json());
    }
    
    $response->assertStatus(201)
        ->assertJsonPath('data.method_code', 'cash')
        ->assertJsonPath('data.amount', 11900)
        ->assertJsonPath('data.tip_amount', 1190)
        ->assertJsonPath('data.total_amount', 13090)
        ->assertJsonPath('data.status', 'completed');
});

test('POST /api/v1/billing/payments es idempotente con misma key', function () {
    $order = paymentApiCreateServedOrder($this);
    $idempotencyKey = Str::uuid()->toString();

    $response1 = $this->withHeaders(paymentApiHeaders())
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $order->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 11900,
            'idempotency_key' => $idempotencyKey,
        ]);

    $response1->assertStatus(201);
    $paymentUuid1 = $response1->json('data.uuid');

    $response2 = $this->withHeaders(paymentApiHeaders())
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $order->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 11900,
            'idempotency_key' => $idempotencyKey,
        ]);

    $response2->assertStatus(201);
    $paymentUuid2 = $response2->json('data.uuid');

    expect($paymentUuid1)->toBe($paymentUuid2);
});

test('POST /api/v1/billing/payments requiere idempotency_key', function () {
    $order = paymentApiCreateServedOrder($this);

    $response = $this->withHeaders(paymentApiHeaders())
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $order->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 11900,
        ]);

    $response->assertStatus(422);
});

test('POST /api/v1/billing/payments deniega pago mayor al total', function () {
    $order = paymentApiCreateServedOrder($this);

    $response = $this->withHeaders(paymentApiHeaders())
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $order->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 99999,
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    $response->assertStatus(422);
});

test('POST /api/v1/billing/payments con tarjeta requiere referencia', function () {
    $order = paymentApiCreateServedOrder($this);

    $response = $this->withHeaders(paymentApiHeaders())
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $order->uuid,
            'payment_method_uuid' => $this->cardMethod->uuid,
            'amount' => 11900,
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    $response->assertStatus(422);
});

// ============================================
// Cross-tenant isolation
// ============================================

test('POST /api/v1/billing/payments usuario B no puede pagar orden de empresa A', function () {
    $orderA = paymentApiCreateServedOrder($this);

    $response = $this->withHeaders(paymentApiHeaders($this->tokenB))
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $orderA->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 11900,
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    expect($response->status())->toBeIn([403, 404, 422]);
});

test('POST /api/v1/billing/payments usuario B no puede usar payment method de empresa A', function () {
    $orderB = paymentApiCreateServedOrderB($this);

    $response = $this->withHeaders(paymentApiHeaders($this->tokenB))
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $orderB->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 5950,
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    expect($response->status())->toBeIn([403, 404, 422]);
});

// ============================================
// TEST: Trazabilidad de pagos a sesión de caja
// ============================================

test('POST /api/v1/billing/payments vincula pago a sesión de caja abierta', function () {
    $order = paymentApiCreateServedOrder($this);

    // La sesión ya está abierta en beforeEach (enableAllCapabilities activa requires_cashier_session)
    $openSession = \Modules\Payments\Domain\Entities\CashSession::where('company_id', $this->company->id)
        ->where('branch_id', $this->branch->id)
        ->where('status', \Modules\Payments\Domain\ValueObjects\CashSessionStatus::OPEN)
        ->first();

    expect($openSession)->not->toBeNull();

    $response = $this->withHeaders(paymentApiHeaders())
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $order->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 11900,
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    $response->assertStatus(201);
    
    $paymentUuid = $response->json('data.uuid');
    $payment = \Modules\Payments\Domain\Entities\Payment::where('uuid', $paymentUuid)->first();

    // Verificar que el pago esté vinculado a la sesión de caja correcta
    expect($payment->cash_session_id)->toBe($openSession->id);
});

test('POST /api/v1/billing/payments sin sesión abierta cuando capability OFF guarda cash_session_id NULL', function () {
    // Crear empresa sin requires_cashier_session
    $companyNoCash = Company::create([
        'tax_id' => 'NO-CASH-' . uniqid(),
        'legal_name' => 'No Cash Control Company',
        'trade_name' => 'No Cash Control',
    ]);

    enableCapabilities($companyNoCash, ['can_accept_tips', 'can_print_receipts']);

    $branchNoCash = Branch::create([
        'company_id' => $companyNoCash->id,
        'code' => 'NO-CASH',
        'name' => 'No Cash Branch',
    ]);

    $cashierNoCash = User::create([
        'name' => 'Cashier No Cash',
        'email' => 'cashier-no-cash-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $companyNoCash->id,
        'branch_id' => $branchNoCash->id,
        'role' => 'cashier',
    ]);

    $cashMethodNoCash = PaymentMethod::create([
        'company_id' => $companyNoCash->id,
        'code' => 'cash-no-cash',
        'name_translations' => ['es' => 'Efectivo No Cash'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    $order = Order::create([
        'company_id' => $companyNoCash->id,
        'branch_id' => $branchNoCash->id,
        'order_number' => 'ORD-NO-CASH-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::SERVED,
        'waiter_id' => $cashierNoCash->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    $token = JWTAuth::fromUser($cashierNoCash);

    // NO abrir sesión de caja (capability OFF)
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ])->postJson('/api/v1/billing/payments', [
        'order_uuid' => $order->uuid,
        'payment_method_uuid' => $cashMethodNoCash->uuid,
        'amount' => 11900,
        'idempotency_key' => Str::uuid()->toString(),
    ]);

    $response->assertStatus(201);
    
    $paymentUuid = $response->json('data.uuid');
    $payment = \Modules\Payments\Domain\Entities\Payment::where('uuid', $paymentUuid)->first();

    // Verificar que cash_session_id sea NULL (sin control de caja)
    expect($payment->cash_session_id)->toBeNull();
});

// ============================================
// TESTS: can_accept_tips capability
// ============================================

test('POST /api/v1/billing/payments rechaza tip_amount si can_accept_tips OFF', function () {
    // Crear empresa SIN can_accept_tips
    $companyNoTips = \Modules\Companies\Domain\Entities\Company::create([
        'tax_id' => 'NO-TIPS-' . uniqid(),
        'legal_name' => 'No Tips Company',
        'trade_name' => 'No Tips',
    ]);

    enableCapabilities($companyNoTips, ['requires_cashier_session', 'can_print_receipts']);

    $branchNoTips = \Modules\Branches\Domain\Entities\Branch::create([
        'company_id' => $companyNoTips->id,
        'code' => 'NO-TIPS',
        'name' => 'No Tips Branch',
    ]);

    $waiter = \Modules\Identity\Domain\Entities\User::create([
        'name' => 'Waiter No Tips',
        'email' => 'waiter-no-tips-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $companyNoTips->id,
        'branch_id' => $branchNoTips->id,
        'role' => 'waiter',
    ]);

    $cashier = \Modules\Identity\Domain\Entities\User::create([
        'name' => 'Cashier No Tips',
        'email' => 'cashier-no-tips-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $companyNoTips->id,
        'branch_id' => $branchNoTips->id,
        'role' => 'cashier',
    ]);

    $cashMethod = \Modules\Payments\Domain\Entities\PaymentMethod::create([
        'company_id' => $companyNoTips->id,
        'code' => 'CASH',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    // Abrir sesión de caja (requires_cashier_session ON)
    \Modules\Payments\Domain\Entities\CashSession::create([
        'company_id' => $companyNoTips->id,
        'branch_id' => $branchNoTips->id,
        'user_id' => $cashier->id,
        'session_number' => 'CS-NO-TIPS-' . strtoupper(substr(uniqid(), -6)),
        'status' => \Modules\Payments\Domain\ValueObjects\CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $order = \Modules\Orders\Domain\Entities\Order::create([
        'company_id' => $companyNoTips->id,
        'branch_id' => $branchNoTips->id,
        'order_number' => 'ORD-NO-TIPS-' . uniqid(),
        'type' => 'dine_in',
        'status' => \Modules\Orders\Domain\ValueObjects\OrderStatus::SERVED,
        'waiter_id' => $waiter->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    $token = \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser($cashier);

    // Intentar pagar CON propina (debe fallar con 422)
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ])->postJson('/api/v1/billing/payments', [
        'order_uuid' => $order->uuid,
        'payment_method_uuid' => $cashMethod->uuid,
        'amount' => 11900,
        'tip_amount' => 2000, // Propina cuando capability OFF
        'idempotency_key' => Str::uuid()->toString(),
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.tip_amount.0', 'Esta empresa no acepta propinas. La capability can_accept_tips está deshabilitada.');
});

test('POST /api/v1/billing/payments acepta tip_amount si can_accept_tips ON', function () {
    // Crear empresa CON can_accept_tips
    $companyWithTips = \Modules\Companies\Domain\Entities\Company::create([
        'tax_id' => 'WITH-TIPS-' . uniqid(),
        'legal_name' => 'With Tips Company',
        'trade_name' => 'With Tips',
    ]);

    enableCapabilities($companyWithTips, ['requires_cashier_session', 'can_accept_tips', 'can_print_receipts']);

    $branchWithTips = \Modules\Branches\Domain\Entities\Branch::create([
        'company_id' => $companyWithTips->id,
        'code' => 'WITH-TIPS',
        'name' => 'With Tips Branch',
    ]);

    $waiter = \Modules\Identity\Domain\Entities\User::create([
        'name' => 'Waiter With Tips',
        'email' => 'waiter-with-tips-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $companyWithTips->id,
        'branch_id' => $branchWithTips->id,
        'role' => 'waiter',
    ]);

    $cashier = \Modules\Identity\Domain\Entities\User::create([
        'name' => 'Cashier With Tips',
        'email' => 'cashier-with-tips-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $companyWithTips->id,
        'branch_id' => $branchWithTips->id,
        'role' => 'cashier',
    ]);

    $cashMethod = \Modules\Payments\Domain\Entities\PaymentMethod::create([
        'company_id' => $companyWithTips->id,
        'code' => 'CASH',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    // Abrir sesión de caja
    \Modules\Payments\Domain\Entities\CashSession::create([
        'company_id' => $companyWithTips->id,
        'branch_id' => $branchWithTips->id,
        'user_id' => $cashier->id,
        'session_number' => 'CS-WITH-TIPS-' . strtoupper(substr(uniqid(), -6)),
        'status' => \Modules\Payments\Domain\ValueObjects\CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $order = \Modules\Orders\Domain\Entities\Order::create([
        'company_id' => $companyWithTips->id,
        'branch_id' => $branchWithTips->id,
        'order_number' => 'ORD-WITH-TIPS-' . uniqid(),
        'type' => 'dine_in',
        'status' => \Modules\Orders\Domain\ValueObjects\OrderStatus::SERVED,
        'waiter_id' => $waiter->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    $token = \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser($cashier);

    // Pagar CON propina (debe funcionar)
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ])->postJson('/api/v1/billing/payments', [
        'order_uuid' => $order->uuid,
        'payment_method_uuid' => $cashMethod->uuid,
        'amount' => 11900,
        'tip_amount' => 2000,
        'idempotency_key' => Str::uuid()->toString(),
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.tip_amount', 2000)
        ->assertJsonPath('data.total_amount', 13900); // 11900 + 2000
});

test('POST /api/v1/billing/payments funciona sin tip_amount independientemente de can_accept_tips', function () {
    // Crear empresa SIN can_accept_tips
    $companyNoTips = \Modules\Companies\Domain\Entities\Company::create([
        'tax_id' => 'NO-TIPS-2-' . uniqid(),
        'legal_name' => 'No Tips 2 Company',
        'trade_name' => 'No Tips 2',
    ]);

    enableCapabilities($companyNoTips, ['requires_cashier_session', 'can_print_receipts']);

    $branchNoTips = \Modules\Branches\Domain\Entities\Branch::create([
        'company_id' => $companyNoTips->id,
        'code' => 'NO-TIPS-2',
        'name' => 'No Tips 2 Branch',
    ]);

    $waiter = \Modules\Identity\Domain\Entities\User::create([
        'name' => 'Waiter No Tips 2',
        'email' => 'waiter-no-tips-2-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $companyNoTips->id,
        'branch_id' => $branchNoTips->id,
        'role' => 'waiter',
    ]);

    $cashier = \Modules\Identity\Domain\Entities\User::create([
        'name' => 'Cashier No Tips 2',
        'email' => 'cashier-no-tips-2-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $companyNoTips->id,
        'branch_id' => $branchNoTips->id,
        'role' => 'cashier',
    ]);

    $cashMethod = \Modules\Payments\Domain\Entities\PaymentMethod::create([
        'company_id' => $companyNoTips->id,
        'code' => 'CASH',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    // Abrir sesión de caja
    \Modules\Payments\Domain\Entities\CashSession::create([
        'company_id' => $companyNoTips->id,
        'branch_id' => $branchNoTips->id,
        'user_id' => $cashier->id,
        'session_number' => 'CS-NO-TIPS-2-' . strtoupper(substr(uniqid(), -6)),
        'status' => \Modules\Payments\Domain\ValueObjects\CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $order = \Modules\Orders\Domain\Entities\Order::create([
        'company_id' => $companyNoTips->id,
        'branch_id' => $branchNoTips->id,
        'order_number' => 'ORD-NO-TIPS-2-' . uniqid(),
        'type' => 'dine_in',
        'status' => \Modules\Orders\Domain\ValueObjects\OrderStatus::SERVED,
        'waiter_id' => $waiter->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    $token = \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser($cashier);

    // Pagar SIN propina (debe funcionar incluso sin capability)
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ])->postJson('/api/v1/billing/payments', [
        'order_uuid' => $order->uuid,
        'payment_method_uuid' => $cashMethod->uuid,
        'amount' => 11900,
        'idempotency_key' => Str::uuid()->toString(),
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.tip_amount', 0)
        ->assertJsonPath('data.total_amount', 11900);
});
