<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Modules\Payments\Domain\ValueObjects\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'REP-' . uniqid(),
        'legal_name' => 'Report Test Company',
        'trade_name' => 'Report Test',
    ]);

    enableAllCapabilities($this->company);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'REP-BR',
        'name' => 'Report Branch',
    ]);

    $this->cashier = User::create([
        'name' => 'Cashier',
        'email' => 'cashier-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    $this->waiter = User::create([
        'name' => 'Waiter',
        'email' => 'waiter-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->cashMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'CASH',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    $this->cardMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'CARD',
        'name_translations' => ['es' => 'Tarjeta'],
        'type' => 'card',
        'is_active' => true,
    ]);

    $this->session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'session_number' => 'CS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $this->token = JWTAuth::fromUser($this->cashier);
});

test('z-report incluye pagos vinculados a la sesión (fix de trazabilidad)', function () {
    // Crear orden
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    // Crear pago vinculado a la sesión (usando el endpoint real)
    $paymentResponse = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ])->postJson('/api/v1/billing/payments', [
        'order_uuid' => $order->uuid,
        'payment_method_uuid' => $this->cashMethod->uuid,
        'amount' => 11900,
        'idempotency_key' => Str::uuid()->toString(),
    ]);

    $paymentResponse->assertStatus(201);

    // Verificar que el pago se vinculó a la sesión
    $payment = Payment::where('uuid', $paymentResponse->json('data.uuid'))->first();
    expect($payment->cash_session_id)->toBe($this->session->id);
    expect($payment->status)->toBe(PaymentStatus::COMPLETED);

    // Solicitar Z-report
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson("/api/v1/cashier/reports/z-report/{$this->session->uuid}");

    $response->assertOk()
        ->assertJsonPath('report_type', 'z-report')
        ->assertJsonPath('payments.cash.amount', 11900)
        ->assertJsonPath('payments.cash.count', 1);

    // Verificar que el expected_cash incluye el pago
    $expectedCash = 50000 + 11900; // opening + payment
    $response->assertJsonPath('expected_cash', $expectedCash);
});

test('z-report NO incluye pagos de otras sesiones', function () {
    // Crear otra sesión
    $otherSession = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'session_number' => 'CS-OTHER-' . strtoupper(substr(uniqid(), -6)),
        'status' => CashSessionStatus::CLOSED,
        'opening_amount' => 30000,
        'opened_at' => now()->subDay(),
        'closed_at' => now()->subHour(),
    ]);

    // Crear orden para el pago
    $otherOrder = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-OTHER-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 5000,
        'tax_amount' => 950,
        'total' => 5950,
    ]);

    // Crear pago en la otra sesión (con todos los campos NOT NULL)
    Payment::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $otherOrder->id,
        'payment_method_id' => $this->cashMethod->id,
        'user_id' => $this->cashier->id,
        'cash_session_id' => $otherSession->id, // ← Pago en OTRA sesión
        'payment_number' => 'PAY-TEST-' . uniqid(),
        'method_code' => 'CASH',
        'amount' => 5950,
        'tip_amount' => 0,
        'total_amount' => 5950,
        'status' => 'completed',
        'idempotency_key' => Str::uuid()->toString(),
    ]);

    // Solicitar Z-report de la sesión actual
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson("/api/v1/cashier/reports/z-report/{$this->session->uuid}");

    $response->assertOk()
        ->assertJsonPath('payments.cash.amount', 0) // ← No debe incluir pago de otra sesión
        ->assertJsonPath('payments.cash.count', 0);

    $expectedCash = 50000; // Solo opening, sin pagos
    $response->assertJsonPath('expected_cash', $expectedCash);
});

test('x-report muestra resumen de sesión abierta', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson('/api/v1/cashier/reports/x-report');

    $response->assertOk()
        ->assertJsonPath('report_type', 'x-report')
        ->assertJsonPath('session_status', 'open')
        ->assertJsonPath('session.uuid', $this->session->uuid);
});

test('z-report retorna 404 si sesión no existe', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson('/api/v1/cashier/reports/z-report/' . Str::uuid());

    $response->assertStatus(404);
});
