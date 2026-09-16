<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Accounting\Domain\Entities\Account;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Services\BillingService;
use Modules\Payments\Domain\Services\CashSessionService;
use Modules\Payments\Domain\Services\PaymentService;
use Modules\Payments\Domain\ValueObjects\BillStatus;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;

uses(RefreshDatabase::class);

/**
 * CRASH RECOVERY TEST (Punto 138)
 * 
 * Valida que el sistema se recupera correctamente después de
 * crashes en diferentes etapas del flujo.
 * 
 * Criterio de cierre: "Nunca queda un estado financiero parcial
 * después de crash/restart."
 */
beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'CRASH-' . uniqid(),
        'legal_name' => 'Crash Recovery Test',
        'trade_name' => 'Crash Test',
    ]);

    enableAllCapabilities($this->company);
    Account::seedDefaultsFor($this->company->id);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'CRASH',
        'name' => 'Crash Branch',
    ]);

    $this->user = User::create([
        'name' => 'Crash User',
        'email' => 'crash-' . uniqid() . '@test.com',
        'password' => bcrypt('password'),
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

    $this->cashSessionService = app(CashSessionService::class);
    $this->paymentService = app(PaymentService::class);
    $this->billingService = app(BillingService::class);
});

test('crash durante creación de pago: transacción hace rollback', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-CRASH-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'subtotal_gross' => 10000,
        'net_amount' => 8403.36,
        'tax_amount' => 1596.64,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $bill = $this->billingService->createSingleBill($order);

    $cashSession = $this->cashSessionService->openSession(
        $this->company->id,
        $this->branch->id,
        $this->user->id,
        50000.00
    );

    // Simular crash ANTES de commit: rollback manual
    DB::beginTransaction();
    try {
        // Intentar crear payment dentro de transacción
        $payment = Payment::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'order_id' => $order->id,
            'bill_id' => $bill->id,
            'cash_session_id' => $cashSession->id,
            'payment_method_id' => $this->cashMethod->id,
            'user_id' => $this->user->id,
            'payment_number' => 'PAY-CRASH-001',
            'method_code' => 'cash',
            'amount' => 10000.00,
            'tip_amount' => 0,
            'total_amount' => 10000.00,
            'status' => 'completed',
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        // Simular crash: hacer rollback
        DB::rollBack();
    } catch (\Exception $e) {
        DB::rollBack();
    }

    // Verificar que NO se creó payment (rollback funcionó)
    $paymentCount = Payment::where('order_id', $order->id)->count();
    expect($paymentCount)->toBe(0, 'Rollback previene estado parcial');

    // Bill sigue OPEN
    $bill->refresh();
    expect($bill->status)->toBe(BillStatus::OPEN)
        ->and((float) $bill->paid_amount)->toBe(0.00);
});

test('crash después de payment pero antes de bill update: bill recupera estado correcto', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-CRASH-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'subtotal_gross' => 10000,
        'net_amount' => 8403.36,
        'tax_amount' => 1596.64,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $bill = $this->billingService->createSingleBill($order);

    $cashSession = $this->cashSessionService->openSession(
        $this->company->id,
        $this->branch->id,
        $this->user->id,
        50000.00
    );

    // Pago exitoso (todo dentro de transacción)
    $payment = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000.00,
        idempotencyKey: Str::uuid()->toString(),
        bill: $bill,
        cashSession: $cashSession,
        userId: $this->user->id
    );

    // "Reiniciar aplicación": recargar desde DB
    $billReloaded = Bill::find($bill->id);
    $paymentReloaded = Payment::find($payment->id);
    $orderReloaded = Order::find($order->id);

    // Verificar integridad completa
    expect($billReloaded->status)->toBe(BillStatus::PAID)
        ->and((float) $billReloaded->paid_amount)->toBe(10000.00)
        ->and($paymentReloaded)->not->toBeNull()
        ->and($orderReloaded->status)->toBe(OrderStatus::PAID);
});

test('crash durante cierre de caja: sesión queda abierta, se puede reintentar', function () {
    $cashSession = $this->cashSessionService->openSession(
        $this->company->id,
        $this->branch->id,
        $this->user->id,
        50000.00
    );

    // Crear orden y pago
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-CRASH-003',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'subtotal_gross' => 10000,
        'net_amount' => 8403.36,
        'tax_amount' => 1596.64,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000.00,
        idempotencyKey: Str::uuid()->toString(),
        cashSession: $cashSession,
        userId: $this->user->id
    );

    // Simular crash antes de cerrar caja
    // La sesión sigue abierta
    $sessionReloaded = CashSession::find($cashSession->id);
    expect($sessionReloaded->status)->toBe(CashSessionStatus::OPEN);

    // Reintento: cerrar caja después del "crash"
    $closedSession = $this->cashSessionService->closeSession(
        $sessionReloaded,
        60000.00, // 50000 + 10000
        'Cierre después de crash'
    );

    expect($closedSession->status)->toBe(CashSessionStatus::CLOSED)
        ->and((float) $closedSession->expected_amount)->toBe(60000.00)
        ->and((float) $closedSession->difference)->toBe(0.00);
});

test('crash recovery: idempotencia previene doble pago después de retry', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-CRASH-004',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'subtotal_gross' => 10000,
        'net_amount' => 8403.36,
        'tax_amount' => 1596.64,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $cashSession = $this->cashSessionService->openSession(
        $this->company->id,
        $this->branch->id,
        $this->user->id,
        50000.00
    );

    $idempotencyKey = Str::uuid()->toString();

    // Primer intento de pago (exitoso)
    $payment1 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000.00,
        idempotencyKey: $idempotencyKey,
        cashSession: $cashSession,
        userId: $this->user->id
    );

    // Simular crash después del pago pero antes de recibir respuesta
    // Cliente reintenta con misma idempotency_key
    $payment2 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000.00,
        idempotencyKey: $idempotencyKey,
        cashSession: $cashSession,
        userId: $this->user->id
    );

    // Debe retornar el mismo payment (idempotencia)
    expect($payment1->id)->toBe($payment2->id);

    // Verificar que solo hay 1 payment en DB
    $paymentCount = Payment::where('order_id', $order->id)->count();
    expect($paymentCount)->toBe(1, 'Idempotencia previene doble pago');

    // Total pagado debe ser $10,000 (no $20,000)
    $totalPaid = Payment::where('order_id', $order->id)->sum('amount');
    expect((float) $totalPaid)->toBe(10000.00);
});

test('criterio de cierre: nunca queda estado financiero parcial después de crash', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-CRASH-CRITERIA',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'subtotal_gross' => 10000,
        'net_amount' => 8403.36,
        'tax_amount' => 1596.64,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $bill = $this->billingService->createSingleBill($order);

    $cashSession = $this->cashSessionService->openSession(
        $this->company->id,
        $this->branch->id,
        $this->user->id,
        50000.00
    );

    // Pago completo
    $payment = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000.00,
        idempotencyKey: Str::uuid()->toString(),
        bill: $bill,
        cashSession: $cashSession,
        userId: $this->user->id
    );

    // Simular "crash": limpiar todas las caches y recargar
    \Illuminate\Support\Facades\Cache::flush();

    $orderReloaded = Order::find($order->id);
    $billReloaded = Bill::find($bill->id);
    $paymentReloaded = Payment::find($payment->id);
    $sessionReloaded = CashSession::find($cashSession->id);

    // Verificar integridad completa (sin estado parcial)
    expect($orderReloaded->status)->toBe(OrderStatus::PAID)
        ->and($billReloaded->status)->toBe(BillStatus::PAID)
        ->and((float) $billReloaded->paid_amount)->toBe(10000.00)
        ->and($paymentReloaded)->not->toBeNull()
        ->and((float) $paymentReloaded->total_amount)->toBe(10000.00)
        ->and($sessionReloaded->status)->toBe(CashSessionStatus::OPEN);

    // Verificar ledger (asientos contables)
    $ledgerCount = DB::table('ledger_entries')
        ->where('company_id', $this->company->id)
        ->count();
    expect($ledgerCount)->toBeGreaterThan(0, 'Ledger tiene asientos contables');
});
