<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Cashier\Domain\Entities\CashRegister;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Modules\Cashier\Domain\Entities\TipPayout;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\PaymentMethod;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'TIP-' . uniqid(),
        'legal_name' => 'Tip Test Company',
        'trade_name' => 'Tip Test Restaurant',
    ]);

    enableAllCapabilities($this->company);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'TIP-BR',
        'name' => 'Tip Branch',
    ]);

    $this->cashier = User::create([
        'name' => 'Cashier',
        'email' => 'cashier-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    $this->waiter1 = User::create([
        'name' => 'Waiter 1',
        'email' => 'waiter1-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->waiter2 = User::create([
        'name' => 'Waiter 2',
        'email' => 'waiter2-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    // Crear caja registradora
    $this->register = CashRegister::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Test Register',
        'code' => 'REG-001',
        'is_active' => true,
    ]);

    // Crear método de pago para los tests
    $this->cashMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'cash',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    // Crear sesión de caja con todos los campos requeridos
    $this->session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'register_id' => $this->register->id,
        'session_number' => 'CS-TIP-' . uniqid(),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 100,
        'opened_at' => now(),
    ]);
});

// ============================================
// GET /api/v1/cashier/tip-payouts
// ============================================

test('lista entregas de propinas de la sesión', function () {
    // Crear 2 entregas
    TipPayout::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'cash_session_id' => $this->session->id,
        'processed_by' => $this->cashier->id,
        'waiter_id' => $this->waiter1->id,
        'amount' => 50.00,
        'payment_method' => 'cash',
        'policy_type' => 'waiter_keeps',
    ]);

    TipPayout::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'cash_session_id' => $this->session->id,
        'processed_by' => $this->cashier->id,
        'waiter_id' => $this->waiter2->id,
        'amount' => 30.00,
        'payment_method' => 'cash',
        'policy_type' => 'waiter_keeps',
    ]);

    $token = loginAs($this->cashier);

    $response = $this->withHeaders(authHeaders($token))
        ->getJson('/api/v1/cashier/tip-payouts');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

// ============================================
// POST /api/v1/cashier/tip-payouts
// ============================================

test('crea entrega manual de propinas', function () {
    // Crear orden con propina
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->waiter1->id,
        'order_number' => 'ORD-001-' . now()->format('Ymd') . '-0001',
        'status' => OrderStatus::PAID,
        'subtotal' => 100,
        'tax_amount' => 19,
        'total' => 119,
    ]);

    // Crear pago con propina
    Payment::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'cash_session_id' => $this->session->id,
        'payment_method_id' => $this->cashMethod->id,
        'user_id' => $this->cashier->id,
        'payment_number' => 'PAY-' . uniqid(),
        'idempotency_key' => 'idem-' . uniqid(),
        'method_code' => 'CASH',
        'amount' => 100,
        'tip_amount' => 20,
        'total_amount' => 120,
        'status' => 'completed',
        'paid_at' => now(),
    ]);

    $token = loginAs($this->cashier);

    $response = $this->withHeaders(authHeaders($token))
        ->postJson('/api/v1/cashier/tip-payouts', [
            'waiter_id' => $this->waiter1->id,
            'amount' => 20.00,
            'payment_method' => 'cash',
            'notes' => 'Entrega manual',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.amount', 20)
        ->assertJsonPath('data.waiter.id', $this->waiter1->id);
});

test('rechaza entrega que excede propinas disponibles', function () {
    // Crear orden con propina de 20
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->waiter1->id,
        'order_number' => 'ORD-001-' . now()->format('Ymd') . '-0002',
        'status' => OrderStatus::PAID,
        'subtotal' => 100,
        'tax_amount' => 19,
        'total' => 119,
    ]);

    Payment::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'cash_session_id' => $this->session->id,
        'payment_method_id' => $this->cashMethod->id,
        'user_id' => $this->cashier->id,
        'payment_number' => 'PAY-' . uniqid(),
        'idempotency_key' => 'idem-' . uniqid(),
        'method_code' => 'CASH',
        'amount' => 100,
        'tip_amount' => 20,
        'total_amount' => 120,
        'status' => 'completed',
        'paid_at' => now(),
    ]);

    $token = loginAs($this->cashier);

    $response = $this->withHeaders(authHeaders($token))
        ->postJson('/api/v1/cashier/tip-payouts', [
            'waiter_id' => $this->waiter1->id,
            'amount' => 50.00, // Excede las 20 disponibles
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'insufficient_tips');
});

// ============================================
// DELETE /api/v1/cashier/tip-payouts/{uuid}
// ============================================

test('anula entrega de propinas', function () {
    $payout = TipPayout::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'cash_session_id' => $this->session->id,
        'processed_by' => $this->cashier->id,
        'waiter_id' => $this->waiter1->id,
        'amount' => 50.00,
        'payment_method' => 'cash',
        'policy_type' => 'waiter_keeps',
    ]);

    $token = loginAs($this->cashier);

    $response = $this->withHeaders(authHeaders($token))
        ->deleteJson("/api/v1/cashier/tip-payouts/{$payout->uuid}");

    $response->assertStatus(200);

    // Verificar que fue anulado
    $payout->refresh();
    expect($payout->is_voided)->toBeTrue();
    expect($payout->voided_at)->not->toBeNull();
});

// ============================================
// GET /api/v1/cashier/tips/summary
// ============================================

test('retorna resumen de propinas de la sesión', function () {
    // Crear órdenes con propinas
    $order1 = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->waiter1->id,
        'order_number' => 'ORD-001-' . now()->format('Ymd') . '-0003',
        'status' => OrderStatus::PAID,
        'subtotal' => 100,
        'tax_amount' => 19,
        'total' => 119,
    ]);

    Payment::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order1->id,
        'cash_session_id' => $this->session->id,
        'payment_method_id' => $this->cashMethod->id,
        'user_id' => $this->cashier->id,
        'payment_number' => 'PAY-' . uniqid(),
        'idempotency_key' => 'idem-' . uniqid(),
        'method_code' => 'CASH',
        'amount' => 100,
        'tip_amount' => 20,
        'total_amount' => 120,
        'status' => 'completed',
        'paid_at' => now(),
    ]);

    $token = loginAs($this->cashier);

    $response = $this->withHeaders(authHeaders($token))
        ->getJson('/api/v1/cashier/tips/summary');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'session_id',
                'tips_by_method',
                'total_tips',
                'already_paid_out',
                'pending',
            ],
        ]);
});

// ============================================
// GET /api/v1/cashier/tips/by-waiter
// ============================================

test('retorna propinas agrupadas por garzón', function () {
    $token = loginAs($this->cashier);

    $response = $this->withHeaders(authHeaders($token))
        ->getJson('/api/v1/cashier/tips/by-waiter');

    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
});

// ============================================
// POST /api/v1/cashier/tips/generate-payouts
// ============================================

test('genera automáticamente entregas para todos los garzones', function () {
    // Crear orden con propina
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->waiter1->id,
        'order_number' => 'ORD-001-' . now()->format('Ymd') . '-0004',
        'status' => OrderStatus::PAID,
        'subtotal' => 100,
        'tax_amount' => 19,
        'total' => 119,
    ]);

    Payment::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'cash_session_id' => $this->session->id,
        'payment_method_id' => $this->cashMethod->id,
        'user_id' => $this->cashier->id,
        'payment_number' => 'PAY-' . uniqid(),
        'idempotency_key' => 'idem-' . uniqid(),
        'method_code' => 'CASH',
        'amount' => 100,
        'tip_amount' => 20,
        'total_amount' => 120,
        'status' => 'completed',
        'paid_at' => now(),
    ]);

    $token = loginAs($this->cashier);

    $response = $this->withHeaders(authHeaders($token))
        ->postJson('/api/v1/cashier/tips/generate-payouts');

    $response->assertStatus(201)
        ->assertJsonPath('count', 1);

    // Verificar que se creó el payout
    $this->assertDatabaseHas('tip_payouts', [
        'waiter_id' => $this->waiter1->id,
        'amount' => 20.00,
    ]);
});

// ============================================
// ERROR: Sin sesión abierta
// ============================================

test('retorna error cuando no hay sesión abierta', function () {
    // Cerrar la sesión
    $this->session->update([
        'status' => CashSessionStatus::CLOSED,
        'closed_at' => now(),
    ]);

    $token = loginAs($this->cashier);

    $response = $this->withHeaders(authHeaders($token))
        ->getJson('/api/v1/cashier/tips/summary');

    $response->assertStatus(422)
        ->assertJsonPath('error', 'no_open_session');
});
