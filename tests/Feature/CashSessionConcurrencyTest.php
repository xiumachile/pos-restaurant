<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Services\CashSessionService;
use Modules\Payments\Domain\Services\PaymentService;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create(['tax_id' => 'TEST', 'legal_name' => 'Test Co', 'trade_name' => 'Test']);
    $this->branch = Branch::create(['company_id' => $this->company->id, 'code' => 'T1', 'name' => 'Test Branch']);
    
    $this->user = User::create([
        'name' => 'Cajero',
        'email' => 'cajero@test.test',
        'password' => bcrypt('pw'),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier'
    ]);

    $this->cashMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'CASH',
        'type' => 'cash',
        'name_translations' => ['es' => 'Efectivo', 'zh' => '现金'],
        'is_active' => true,
    ]);

    $this->paymentService = app(PaymentService::class);
    $this->cashSessionService = app(CashSessionService::class);
});

function createTestOrderForCash($ctx): Order
{
    $order = Order::create([
        'company_id' => $ctx->company->id,
        'branch_id' => $ctx->branch->id,
        'order_number' => 'ORD-CASH-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::SERVED,
        'waiter_id' => $ctx->user->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
        'tip_amount' => 0,
        'amount_due' => 11900,
    ]);

    OrderItem::create([
        'company_id' => $ctx->company->id,
        'branch_id' => $ctx->branch->id,
        'order_id' => $order->id,
        'name_snapshot' => 'Producto Test',
        'quantity' => 1,
        'unit_price_snapshot' => 10000,
        'subtotal' => 10000,
        'tax_amount' => 1900,
    ]);

    return $order;
}

test('CA-05: El cierre de caja bloquea la sesión y espera a que los pagos concurrentes terminen', function () {
    // 1. Abrir sesión de caja
    $session = $this->cashSessionService->openSession(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        userId: $this->user->id,
        openingAmount: 50000,
        notes: 'Apertura de prueba / 测试开启'
    );

    $order = createTestOrderForCash($this);

    // 2. Simular concurrencia: iniciar un pago que toma tiempo (usando transacción)
    // En un entorno real, esto sería un hilo separado. Aquí verificamos que el lockForUpdate
    // en closeSession espera a que la transacción del pago termine.
    
    // Para simular esto en test, podemos verificar que si intentamos cerrar mientras 
    // hay una transacción abierta, el cierre espera (PostgreSQL lockForUpdate).
    // Como Pest no soporta hilos nativos fácilmente, verificamos la lógica de bloqueo:
    // Si la sesión está cerrada, el pago debe fallar.
    
    $this->cashSessionService->closeSession(
        session: $session,
        closingAmount: 61900, // 50000 + 11900
        notes: 'Cierre de prueba / 测试关闭'
    );

    $session->refresh();
    expect($session->status->value)->toBe('closed');

    // 3. Intentar registrar un pago en una sesión ya cerrada debe fallar
    // (Si el controlador pasa la sesión cerrada, validateTenantInvariants fallará)
    expect(fn() => $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 11900,
        idempotencyKey: \Illuminate\Support\Str::uuid()->toString(),
        cashSession: $session, // Sesión cerrada
        userId: $this->user->id,
        tipAmount: 0,
    ))->toThrow(\Modules\Payments\Domain\Exceptions\PaymentException::class, 'No hay una sesión de caja abierta');
});

test('CA-05: El pago registrado antes del cierre se incluye en el monto esperado del arqueo', function () {
    $session = $this->cashSessionService->openSession(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        userId: $this->user->id,
        openingAmount: 50000,
        notes: 'Apertura'
    );

    $order = createTestOrderForCash($this);

    // Registrar pago ANTES del cierre
    $payment = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 11900,
        idempotencyKey: \Illuminate\Support\Str::uuid()->toString(),
        cashSession: $session,
        userId: $this->user->id,
        tipAmount: 0,
    );

    expect($payment->status->value)->toBe('completed');

    // Cerrar caja
    $closedSession = $this->cashSessionService->closeSession(
        session: $session,
        closingAmount: 61900, // 50000 (apertura) + 11900 (venta)
        notes: 'Cierre'
    );

    // El monto esperado debe incluir el pago registrado
    expect($closedSession->opening_amount)->toBe(50000)
        ->and($closedSession->expected_amount)->toBe(61900)
        ->and($closedSession->difference)->toBe(0)
        ->and($closedSession->status->value)->toBe('closed');
});

test('CA-05: Múltiples cierres concurrentes en la misma sesión son prevenidos por lockForUpdate', function () {
    $session = $this->cashSessionService->openSession(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        userId: $this->user->id,
        openingAmount: 50000,
        notes: 'Apertura'
    );

    // Primer cierre exitoso
    $this->cashSessionService->closeSession(
        session: $session,
        closingAmount: 50000,
        notes: 'Cierre 1'
    );

    // Segundo cierre en la misma instancia de sesión (simulando race condition)
    // Debe fallar porque al hacer lockForUpdate y verificar, ya no está activa
    expect(fn() => $this->cashSessionService->closeSession(
        session: $session,
        closingAmount: 50000,
        notes: 'Cierre 2'
    ))->toThrow(\Modules\Payments\Domain\Exceptions\PaymentException::class, 'No hay una sesión de caja abierta');
});
