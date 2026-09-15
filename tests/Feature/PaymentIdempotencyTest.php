<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Accounting\Domain\Entities\Account;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Services\PaymentService;

uses(RefreshDatabase::class);

/**
 * PAYMENT IDEMPOTENCY TEST (Puntos 57-67)
 * 
 * Valida:
 * - Doble click no crea doble pago
 * - Timeout + retry no crea doble pago
 * - Mismo key + mismo payload → mismo resultado
 * - Mismo key + diferente payload → error (o mismo resultado)
 * - Concurrencia multi-terminal no crea doble pago
 */
beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'PIDEM-' . uniqid(),
        'legal_name' => 'Payment Idempotency Test',
        'trade_name' => 'PIDEM Test',
    ]);

    enableAllCapabilities($this->company);
    Account::seedDefaultsFor($this->company->id);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'PIDEM',
        'name' => 'PIDEM Branch',
    ]);

    $this->user = User::create([
        'name' => 'Cashier',
        'email' => 'pidem-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    $this->cashMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'cash',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    $this->paymentService = app(PaymentService::class);
});

// ═══════════════════════════════════════════════════
// PUNTO 60: Idempotencia básica
// ═══════════════════════════════════════════════════
test('Mismo idempotency_key + mismo payload retorna mismo payment (sin duplicar)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'PIDEM-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->user->id,
        'subtotal_gross' => 10000,
        'net_amount' => 8403.36,
        'tax_amount' => 1596.64,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $idempotencyKey = Str::uuid()->toString();

    // Primer pago
    $payment1 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000,
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id,
        tipAmount: 0
    );

    // Segundo intento con misma key y mismo payload (simula retry)
    $payment2 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000,
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id,
        tipAmount: 0
    );

    // Debe retornar el mismo payment (idempotencia)
    expect($payment1->id)->toBe($payment2->id)
        ->and($payment1->uuid)->toBe($payment2->uuid)
        ->and($payment1->idempotency_key)->toBe($payment2->idempotency_key);

    // Solo debe existir 1 payment en DB
    $count = \Modules\Payments\Domain\Entities\Payment::where('order_id', $order->id)->count();
    expect($count)->toBe(1, 'Idempotencia previene doble pago');
});

// ═══════════════════════════════════════════════════
// PUNTO 62: Mismo key + mismo payload = mismo resultado
// ═══════════════════════════════════════════════════
test('Mismo key + mismo payload produce exactamente el mismo resultado', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'PIDEM-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->user->id,
        'subtotal_gross' => 10000,
        'net_amount' => 8403.36,
        'tax_amount' => 1596.64,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $idempotencyKey = Str::uuid()->toString();

    $payment1 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000,
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id,
        tipAmount: 500
    );

    // Reintentar con exactamente los mismos parámetros
    $payment2 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000,
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id,
        tipAmount: 500
    );

    // Todos los campos deben coincidir
    expect((float) $payment1->amount)->toBe((float) $payment2->amount)
        ->and((float) $payment1->tip_amount)->toBe((float) $payment2->tip_amount)
        ->and((float) $payment1->total_amount)->toBe((float) $payment2->total_amount)
        ->and($payment1->status)->toBe($payment2->status)
        ->and($payment1->id)->toBe($payment2->id);
});

// ═══════════════════════════════════════════════════
// PUNTO 63: Mismo key + diferente payload
// ═══════════════════════════════════════════════════
test('Mismo key + diferente payload retorna payment existente (sin validar payload)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'PIDEM-003',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->user->id,
        'subtotal_gross' => 10000,
        'net_amount' => 8403.36,
        'tax_amount' => 1596.64,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $idempotencyKey = Str::uuid()->toString();

    // Primer pago: $5,000
    $payment1 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 5000,
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id,
        tipAmount: 0
    );

    // Segundo intento con MISMA key pero DIFERENTE amount ($7,000)
    // Comportamiento actual: retorna el existente sin validar payload
    $payment2 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 7000,  // Diferente al primer pago
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id,
        tipAmount: 0
    );

    // Actualmente retorna el primer payment (no valida payload)
    expect($payment1->id)->toBe($payment2->id)
        ->and((float) $payment2->amount)->toBe(5000.00, 'Retorna primer pago, no valida payload');

    // Solo existe 1 payment
    $count = \Modules\Payments\Domain\Entities\Payment::where('order_id', $order->id)->count();
    expect($count)->toBe(1);
})->todo('Considerar: ¿debería validar que payload coincida y lanzar error?');

// ═══════════════════════════════════════════════════
// PUNTO 64: Doble click no crea doble pago
// ═══════════════════════════════════════════════════
test('Doble click rápido no crea doble pago (idempotencia protege)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'PIDEM-004',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->user->id,
        'subtotal_gross' => 10000,
        'net_amount' => 8403.36,
        'tax_amount' => 1596.64,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $idempotencyKey = Str::uuid()->toString();

    // Simular doble click: 2 llamadas casi simultáneas
    $payment1 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000,
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id,
        tipAmount: 0
    );

    // Inmediatamente después (simula doble click)
    $payment2 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000,
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id,
        tipAmount: 0
    );

    // Solo debe existir 1 payment
    expect($payment1->id)->toBe($payment2->id);

    $count = \Modules\Payments\Domain\Entities\Payment::where('order_id', $order->id)->count();
    expect($count)->toBe(1, 'Doble click no crea doble pago');
});

// ═══════════════════════════════════════════════════
// PUNTO 67: Timeout + retry
// ═══════════════════════════════════════════════════
test('Timeout + retry no crea doble pago (cliente reusa misma key)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'PIDEM-005',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->user->id,
        'subtotal_gross' => 10000,
        'net_amount' => 8403.36,
        'tax_amount' => 1596.64,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $idempotencyKey = Str::uuid()->toString();

    // Primer intento (simula que el servidor procesó pero el cliente no recibió respuesta)
    $payment1 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000,
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id,
        tipAmount: 0
    );

    // Cliente hace retry con misma key (porque no recibió respuesta)
    $payment2 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000,
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id,
        tipAmount: 0
    );

    // Debe retornar el mismo payment
    expect($payment1->id)->toBe($payment2->id);

    // Solo 1 payment en DB
    $count = \Modules\Payments\Domain\Entities\Payment::where('order_id', $order->id)->count();
    expect($count)->toBe(1, 'Retry con misma key no crea doble pago');
});

// ═══════════════════════════════════════════════════
// PUNTO 67: Concurrencia multi-terminal
// ═══════════════════════════════════════════════════
test('Dos terminales con diferente key pueden pagar el mismo order (pagos parciales)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'PIDEM-006',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->user->id,
        'subtotal_gross' => 10000,
        'net_amount' => 8403.36,
        'tax_amount' => 1596.64,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    // Terminal 1: paga $5,000
    $payment1 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 5000,
        idempotencyKey: Str::uuid()->toString(),  // Key diferente
        userId: $this->user->id,
        tipAmount: 0
    );

    // Terminal 2: paga $5,000 (con key diferente)
    $payment2 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 5000,
        idempotencyKey: Str::uuid()->toString(),  // Key diferente
        userId: $this->user->id,
        tipAmount: 0
    );

    // Deben ser 2 payments diferentes (pagos parciales)
    expect($payment1->id)->not->toBe($payment2->id);

    $count = \Modules\Payments\Domain\Entities\Payment::where('order_id', $order->id)->count();
    expect($count)->toBe(2, 'Dos terminales con keys diferentes crean 2 pagos');

    // Total pagado debe ser $10,000
    $totalPaid = \Modules\Payments\Domain\Entities\Payment::where('order_id', $order->id)->sum('amount');
    expect((float) $totalPaid)->toBe(10000.00);
});

// ═══════════════════════════════════════════════════
// CRITERIO DE CIERRE
// ═══════════════════════════════════════════════════
test('CRITERIO DE CIERRE: Nunca aparece doble pago por retry o concurrencia', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'PIDEM-007',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->user->id,
        'subtotal_gross' => 10000,
        'net_amount' => 8403.36,
        'tax_amount' => 1596.64,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $idempotencyKey = Str::uuid()->toString();

    // Simular 5 reintentos con misma key (doble click + timeout + retry)
    $payments = [];
    for ($i = 0; $i < 5; $i++) {
        $payments[] = $this->paymentService->registerPayment(
            order: $order,
            paymentMethod: $this->cashMethod,
            amount: 10000,
            idempotencyKey: $idempotencyKey,
            userId: $this->user->id,
            tipAmount: 0
        );
    }

    // Todos deben ser el mismo payment
    $firstId = $payments[0]->id;
    foreach ($payments as $payment) {
        expect($payment->id)->toBe($firstId);
    }

    // Solo 1 payment en DB
    $count = \Modules\Payments\Domain\Entities\Payment::where('order_id', $order->id)->count();
    expect($count)->toBe(1, 'CRITERIO DE CIERRE: 5 reintentos = 1 payment');

    // Total pagado debe ser exactamente $10,000 (no $50,000)
    $totalPaid = \Modules\Payments\Domain\Entities\Payment::where('order_id', $order->id)->sum('amount');
    expect((float) $totalPaid)->toBe(10000.00, 'No hay doble cobro');
});
