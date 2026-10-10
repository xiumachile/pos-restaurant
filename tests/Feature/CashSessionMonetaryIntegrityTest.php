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

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'MON-' . uniqid(),
        'legal_name' => 'Monetary Integrity Test',
        'trade_name' => 'Monetary Test',
    ]);

    enableAllCapabilities($this->company);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Monetary Branch',
        'code' => 'MON',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Cashier',
        'email' => 'mon-cashier-' . uniqid() . '@test.com',
        'password' => bcrypt('password'),
        'role' => 'cashier',
    ]);

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

    $this->transferMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Transferencia', 'en' => 'Transfer'],
        'code' => 'transfer',
        'type' => 'transfer',
        'is_active' => true,
    ]);

    $this->cashSessionService = app(CashSessionService::class);
    $this->paymentService = app(PaymentService::class);
});

test('Hallazgo 06: efectivo físico NO incluye tarjeta ni transferencia', function () {
    // Apertura: $50.000
    $session = $this->cashSessionService->openSession(
        $this->company->id,
        $this->branch->id,
        $this->user->id,
        50000,
        'Apertura'
    );

    // Crear orden
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-MON-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 500000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 500000,
    ]);

    // Pago 1: $100.000 EFECTIVO
    $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 100000,
        idempotencyKey: Str::uuid()->toString(),
        bill: null,
        cashSession: $session,
        userId: $this->user->id,
        tipAmount: 0
    );

    // Pago 2: $250.000 TARJETA
    $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cardMethod,
        amount: 250000,
        idempotencyKey: Str::uuid()->toString(),
        bill: null,
        cashSession: $session,
        userId: $this->user->id,
        tipAmount: 0
    );

    // Pago 3: $150.000 TRANSFERENCIA
    $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->transferMethod,
        amount: 150000,
        idempotencyKey: Str::uuid()->toString(),
        bill: null,
        cashSession: $session,
        userId: $this->user->id,
        tipAmount: 0
    );

    $session->refresh();

    // ═══════════════════════════════════════════════════════════
    // VERIFICACIONES CRÍTICAS (Hallazgo 06)
    // ═══════════════════════════════════════════════════════════

    // 1. getCashBalance: solo efectivo físico = apertura + cash = 50k + 100k = 150k
    expect($session->getCashBalance())->toBe(150000);

    // 2. getTotalSalesBalance: total = apertura + todos los pagos = 50k + 500k = 550k
    expect($session->getTotalSalesBalance())->toBe(550000);

    // 3. Breakdown por método
    expect($session->getCashSales())->toBe(100000);
    expect($session->getCardSales())->toBe(250000);
    expect($session->getTransferSales())->toBe(150000);

    // 4. exceedsMaxAmount debe comparar contra EFECTIVO FÍSICO (150k)
    // No contra total de ventas (550k)
    expect($session->exceedsMaxAmount(200000))->toBeFalse(); // 150k < 200k
    expect($session->exceedsMaxAmount(100000))->toBeTrue();  // 150k > 100k
});

test('Hallazgo 06: tipos de retorno son int (no float)', function () {
    $session = $this->cashSessionService->openSession(
        $this->company->id,
        $this->branch->id,
        $this->user->id,
        50000
    );

    expect($session->getCashBalance())->toBeInt();
    expect($session->getTotalSalesBalance())->toBeInt();
    expect($session->getCashSales())->toBeInt();
    expect($session->getCardSales())->toBeInt();
    expect($session->getTransferSales())->toBeInt();
    expect($session->calculatePendingTips())->toBeInt();
    expect($session->exceedsMaxAmount(500000))->toBeBool();
});

test('Hallazgo 06: calculateCurrentBalance marcado como deprecated', function () {
    $reflection = new ReflectionMethod(
        \Modules\Payments\Domain\Entities\CashSession::class,
        'calculateCurrentBalance'
    );
    
    $docComment = $reflection->getDocComment();
    expect($docComment)->toContain('@deprecated');
    expect($docComment)->toContain('Hallazgo 06');
});
