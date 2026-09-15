<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Services\CashSessionService;
use Modules\Payments\Domain\Services\PaymentService;
use Modules\Cashier\Domain\Entities\CashMovement;
use Modules\Cashier\Domain\ValueObjects\MovementType;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'CASH-' . uniqid(),
        'legal_name' => 'Cash Test Company',
        'trade_name' => 'Cash Test',
    ]);

    enableAllCapabilities($this->company);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Cash Test Branch',
        'code' => 'CTB',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Cashier',
        'email' => 'cashier-' . uniqid() . '@test.com',
        'password' => bcrypt('password'),
        'role' => 'cashier',
    ]);

    // FIX 1: name_translations es NOT NULL
    $this->cashMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Efectivo', 'en' => 'Cash'],
        'code' => 'cash',
        'type' => 'cash',
        'is_active' => true,
    ]);

    $this->cardMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Tarjeta', 'en' => 'Card'],
        'code' => 'card',
        'type' => 'card',
        'requires_reference' => true,
        'is_active' => true,
    ]);

    $this->cashSessionService = app(CashSessionService::class);
    $this->paymentService = app(PaymentService::class);
});

test('flujo completo: abrir → vender → cobrar → retirar → cerrar', function () {
    // 1. APERTURA: Abrir caja con $50,000
    $session = $this->cashSessionService->openSession(
        $this->company->id,
        $this->branch->id,
        $this->user->id,
        50000.00,
        'Apertura inicial'
    );

    expect($session->opening_amount)->toBe('50000.00')
        ->and($session->status->value)->toBe('open');

    // 2. VENTA: Crear orden de $10,000
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'subtotal_gross' => 10000,
        'net_amount' => 8403.36,
        'tax_amount' => 1596.64,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    // 3. COBRO: Pago en efectivo de $10,000
    // FIX 3: registerPayment requiere idempotencyKey como 4to parámetro
    $payment = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000.00,
        idempotencyKey: Str::uuid()->toString(),
        bill: null,
        cashSession: $session,
        userId: $this->user->id,
        tipAmount: 0.00
    );

    expect($payment->amount)->toBe('10000.00')
        ->and($payment->cash_session_id)->toBe($session->id);

    // 4. RETIRO: Retirar $5,000 de caja
    // FIX 2: MovementType en vez de CashMovementType
    $movement = CashMovement::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'cash_session_id' => $session->id,
        'user_id' => $this->user->id,
        'type' => MovementType::WITHDRAWAL,
        'amount' => 5000.00,
        'reason' => 'Retiro parcial',
        'balance_after' => 55000.00, // 50000 + 10000 - 5000
    ]);

    expect($movement->amount)->toBe('5000.00')
        ->and($movement->type->value)->toBe('withdrawal');

    // 5. CIERRE: Cerrar caja con conteo de $55,000
    $closedSession = $this->cashSessionService->closeSession(
        $session,
        55000.00,
        'Cierre sin diferencia'
    );

    // Validar cálculos:
    // - Apertura: $50,000
    // - Ventas efectivo: $10,000
    // - Retiros: -$5,000
    // - Esperado: $55,000
    // - Contado: $55,000
    // - Diferencia: $0

    expect($closedSession->status->value)->toBe('closed')
        ->and((float) $closedSession->expected_amount)->toBe(55000.00)
        ->and((float) $closedSession->closing_amount)->toBe(55000.00)
        ->and((float) $closedSession->difference)->toBe(0.00);
});

test('cierre con diferencia positiva (sobrante)', function () {
    $session = $this->cashSessionService->openSession(
        $this->company->id,
        $this->branch->id,
        $this->user->id,
        50000.00
    );

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-002',
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
        bill: null,
        cashSession: $session,
        userId: $this->user->id,
        tipAmount: 0.00
    );

    // Cierre con $62,000 (sobrante de $2,000)
    $closedSession = $this->cashSessionService->closeSession(
        $session,
        62000.00
    );

    // Esperado: 50000 + 10000 = 60000
    // Contado: 62000
    // Diferencia: +2000 (sobrante)
    expect((float) $closedSession->expected_amount)->toBe(60000.00)
        ->and((float) $closedSession->closing_amount)->toBe(62000.00)
        ->and((float) $closedSession->difference)->toBe(2000.00);
});

test('cierre con diferencia negativa (faltante)', function () {
    $session = $this->cashSessionService->openSession(
        $this->company->id,
        $this->branch->id,
        $this->user->id,
        50000.00
    );

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-003',
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
        bill: null,
        cashSession: $session,
        userId: $this->user->id,
        tipAmount: 0.00
    );

    // Cierre con $58,000 (faltante de $2,000)
    $closedSession = $this->cashSessionService->closeSession(
        $session,
        58000.00
    );

    // Esperado: 50000 + 10000 = 60000
    // Contado: 58000
    // Diferencia: -2000 (faltante)
    expect((float) $closedSession->expected_amount)->toBe(60000.00)
        ->and((float) $closedSession->closing_amount)->toBe(58000.00)
        ->and((float) $closedSession->difference)->toBe(-2000.00);
});

test('ventas con tarjeta NO afectan balance de efectivo', function () {
    $session = $this->cashSessionService->openSession(
        $this->company->id,
        $this->branch->id,
        $this->user->id,
        50000.00
    );

    // Venta en efectivo de $10,000
    $order1 = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-004',
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
        order: $order1,
        paymentMethod: $this->cashMethod,
        amount: 10000.00,
        idempotencyKey: Str::uuid()->toString(),
        bill: null,
        cashSession: $session,
        userId: $this->user->id,
        tipAmount: 0.00
    );

    // Venta con tarjeta de $15,000
    $order2 = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-005',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'subtotal_gross' => 15000,
        'net_amount' => 12605.04,
        'tax_amount' => 2394.96,
        'amount_due' => 15000,
        'subtotal' => 15000,
        'total' => 15000,
    ]);

    $this->paymentService->registerPayment(
        order: $order2,
        paymentMethod: $this->cardMethod,
        amount: 15000.00,
        idempotencyKey: Str::uuid()->toString(),
        bill: null,
        cashSession: $session,
        userId: $this->user->id,
        tipAmount: 0.00,
        referenceCode: 'AUTH123'
    );

    // Cierre con $60,000 (solo efectivo)
    $closedSession = $this->cashSessionService->closeSession(
        $session,
        60000.00
    );

    // Esperado: 50000 + 10000 (solo efectivo) = 60000
    // La venta con tarjeta NO afecta el balance de efectivo
    expect((float) $closedSession->expected_amount)->toBe(60000.00)
        ->and((float) $closedSession->closing_amount)->toBe(60000.00)
        ->and((float) $closedSession->difference)->toBe(0.00);
});

test('depósito aumenta balance esperado', function () {
    $session = $this->cashSessionService->openSession(
        $this->company->id,
        $this->branch->id,
        $this->user->id,
        50000.00
    );

    // Depósito de $10,000
    CashMovement::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'cash_session_id' => $session->id,
        'user_id' => $this->user->id,
        'type' => MovementType::DEPOSIT,
        'amount' => 10000.00,
        'reason' => 'Depósito adicional',
        'balance_after' => 60000.00,
    ]);

    // Cierre con $60,000
    $closedSession = $this->cashSessionService->closeSession(
        $session,
        60000.00
    );

    // Esperado: 50000 + 10000 (depósito) = 60000
    expect((float) $closedSession->expected_amount)->toBe(60000.00)
        ->and((float) $closedSession->closing_amount)->toBe(60000.00)
        ->and((float) $closedSession->difference)->toBe(0.00);
});

test('criterio de cierre: atomicidad en cierre de caja', function () {
    $session = $this->cashSessionService->openSession(
        $this->company->id,
        $this->branch->id,
        $this->user->id,
        50000.00
    );

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-006',
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
        bill: null,
        cashSession: $session,
        userId: $this->user->id,
        tipAmount: 0.00
    );

    // Cierre
    $closedSession = $this->cashSessionService->closeSession(
        $session,
        60000.00
    );

    // Validar que todos los campos se actualizaron atómicamente
    expect($closedSession->status->value)->toBe('closed')
        ->and($closedSession->closing_amount)->not->toBeNull()
        ->and($closedSession->expected_amount)->not->toBeNull()
        ->and($closedSession->difference)->not->toBeNull()
        ->and($closedSession->closed_at)->not->toBeNull();

    // Validar que no se puede cerrar dos veces
    expect(fn() => $this->cashSessionService->closeSession($closedSession, 60000.00))
        ->toThrow(\Modules\Payments\Domain\Exceptions\PaymentException::class);
});
