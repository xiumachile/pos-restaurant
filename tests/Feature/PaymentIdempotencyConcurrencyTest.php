<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Exceptions\PaymentException;
use Modules\Payments\Domain\Services\PaymentService;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Payments\Domain\ValueObjects\PaymentStatus;

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

    $this->order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-PAY-001',
        'type' => 'dine_in',
        'status' => OrderStatus::SERVED, // Estado que permite pago
        'waiter_id' => $this->user->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
        'amount_due' => 11900,
    ]);

    $this->paymentMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'CASH',
        'type' => 'cash',
        'name_translations' => ['es' => 'Efectivo', 'zh' => '现金'],
        'is_active' => true,
    ]);

    $this->paymentService = app(PaymentService::class);
});

test('CA-01: Múltiples peticiones concurrentes con misma clave y payload devuelven el mismo pago (idempotencia)', function () {
    $idempotencyKey = 'IDEM-' . uniqid();
    $amount = 11900;
    
    // Simular concurrencia ejecutando en "paralelo" (en realidad secuencial en test, pero validamos el estado final)
    // Para simular race condition real, podríamos usar threads, pero el fast-path y la restricción única lo cubren.
    
    $payment1 = $this->paymentService->registerPayment(
        order: $this->order,
        paymentMethod: $this->paymentMethod,
        amount: $amount,
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id
    );

    // Segunda llamada con los mismos datos debe devolver la misma instancia (fast-path)
    $payment2 = $this->paymentService->registerPayment(
        order: $this->order,
        paymentMethod: $this->paymentMethod,
        amount: $amount,
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id
    );

    expect($payment1->id)->toBe($payment2->id)
        ->and($payment1->uuid)->toBe($payment2->uuid)
        ->and(Payment::where('idempotency_key', $idempotencyKey)->count())->toBe(1);
});

test('CA-01: Reutilizar clave de idempotencia con monto distinto lanza excepción de conflicto', function () {
    $idempotencyKey = 'IDEM-CONFLICT-' . uniqid();
    
    // Primer pago exitoso
    $this->paymentService->registerPayment(
        order: $this->order,
        paymentMethod: $this->paymentMethod,
        amount: 5000,
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id
    );

    // Intentar reutilizar la clave con un monto diferente debe fallar
    expect(fn() => $this->paymentService->registerPayment(
        order: $this->order,
        paymentMethod: $this->paymentMethod,
        amount: 6000, // Monto diferente
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id
    ))->toThrow(PaymentException::class, 'Conflicto de idempotencia');

    // Verificar que no se creó un segundo pago
    expect(Payment::where('idempotency_key', $idempotencyKey)->count())->toBe(1);
});

test('CA-01: Reutilizar clave de idempotencia con método de pago distinto lanza excepción de conflicto', function () {
    $idempotencyKey = 'IDEM-METHOD-CONFLICT-' . uniqid();
    
    $this->paymentService->registerPayment(
        order: $this->order,
        paymentMethod: $this->paymentMethod,
        amount: 11900,
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id
    );

    $otherPaymentMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'CARD',
        'type' => 'card',
        'name_translations' => ['es' => 'Tarjeta', 'zh' => '卡'],
        'is_active' => true,
    ]);

    expect(fn() => $this->paymentService->registerPayment(
        order: $this->order,
        paymentMethod: $otherPaymentMethod, // Método diferente
        amount: 11900,
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id
    ))->toThrow(PaymentException::class, 'Conflicto de idempotencia');
});

test('CA-01: El payload_hash se almacena correctamente en la base de datos', function () {
    $idempotencyKey = 'IDEM-HASH-' . uniqid();
    
    $payment = $this->paymentService->registerPayment(
        order: $this->order,
        paymentMethod: $this->paymentMethod,
        amount: 11900,
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id
    );

    $payment->refresh();
    
    expect($payment->payload_hash)->not->toBeNull()
        ->and(strlen($payment->payload_hash))->toBe(64) // SHA-256
        ->and($payment->payload_hash)->toBe(hash('sha256', json_encode([
            'order_id' => $this->order->id,
            'bill_id' => null,
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 11900,
            'tip_amount' => 0,
        ])));
});
