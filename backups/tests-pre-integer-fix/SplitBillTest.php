<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Accounting\Domain\Entities\Account;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
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

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'SPLIT-' . uniqid(),
        'legal_name' => 'Split Bill Test',
        'trade_name' => 'Split Bill',
    ]);

    enableAllCapabilities($this->company);
    Account::seedDefaultsFor($this->company->id);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'SPLIT',
        'name' => 'Split Bill Branch',
    ]);

    $this->user = User::create([
        'name' => 'Cashier Split',
        'email' => 'split-' . uniqid() . '@test.com',
        'password' => bcrypt('password'),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    $category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Platos'],
        'is_active' => true,
    ]);

    $this->product1 = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Hamburguesa'],
        'price' => 10000,
        'is_active' => true,
    ]);

    $this->product2 = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Pizza'],
        'price' => 12000,
        'is_active' => true,
    ]);

    $this->cashMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'cash',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    $this->billingService = app(BillingService::class);
    $this->paymentService = app(PaymentService::class);
    $this->cashSessionService = app(CashSessionService::class);
});

test('split bill: dividir cuenta en 2 partes iguales', function () {
    // Crear orden con 2 productos ($10,000 + $12,000 = $22,000)
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SPLIT-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'subtotal_gross' => 22000,
        'net_amount' => 18487.40,
        'tax_amount' => 3512.60,
        'amount_due' => 22000,
        'subtotal' => 22000,
        'total' => 22000,
    ]);

    OrderItem::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'product_id' => $this->product1->id,
        'name_snapshot' => 'Hamburguesa',
        'quantity' => 1,
        'unit_price_snapshot' => 10000,
        'subtotal' => 10000,
        'tax_amount' => 1596.64,
    ]);

    OrderItem::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'product_id' => $this->product2->id,
        'name_snapshot' => 'Pizza',
        'quantity' => 1,
        'unit_price_snapshot' => 12000,
        'subtotal' => 12000,
        'tax_amount' => 1915.97,
    ]);

    // Dividir en 2 partes iguales
    $bills = $this->billingService->splitEqual($order, 2);

    expect($bills)->toHaveCount(2);

    // Cada bill debe tener $11,000 (22000 / 2)
    foreach ($bills as $bill) {
        expect((float) $bill->total)->toBe(11000.00)
            ->and($bill->status)->toBe(BillStatus::OPEN)
            ->and($bill->type->value)->toBe('equal_split');
    }

    // Verificar que la suma de bills = total de orden
    $totalBills = array_sum(array_map(fn($b) => (float) $b->total, $bills));
    expect((float) $totalBills)->toBe(22000.00);
});

test('split bill: pagar una de las dos bills parcialmente', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SPLIT-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'subtotal_gross' => 20000,
        'net_amount' => 16806.72,
        'tax_amount' => 3193.28,
        'amount_due' => 20000,
        'subtotal' => 20000,
        'total' => 20000,
    ]);

    $bills = $this->billingService->splitEqual($order, 2);
    $bill1 = $bills[0]; // $10,000
    $bill2 = $bills[1]; // $10,000

    // Abrir sesión de caja
    $cashSession = $this->cashSessionService->openSession(
        $this->company->id,
        $this->branch->id,
        $this->user->id,
        50000.00
    );

    // Pagar $5,000 de bill1 (pago parcial)
    $payment1 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 5000.00,
        idempotencyKey: Str::uuid()->toString(),
        bill: $bill1,
        cashSession: $cashSession,
        userId: $this->user->id
    );

    $bill1->refresh();
    expect($bill1->status)->toBe(BillStatus::PARTIAL)
        ->and((float) $bill1->paid_amount)->toBe(5000.00)
        ->and((float) $bill1->remaining_amount)->toBe(5000.00);

    // bill2 debe seguir OPEN
    $bill2->refresh();
    expect($bill2->status)->toBe(BillStatus::OPEN)
        ->and((float) $bill2->paid_amount)->toBe(0.00)
        ->and((float) $bill2->remaining_amount)->toBe(10000.00);
});

test('split bill: pagar ambas bills completamente', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SPLIT-003',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'subtotal_gross' => 20000,
        'net_amount' => 16806.72,
        'tax_amount' => 3193.28,
        'amount_due' => 20000,
        'subtotal' => 20000,
        'total' => 20000,
    ]);

    $bills = $this->billingService->splitEqual($order, 2);
    $bill1 = $bills[0];
    $bill2 = $bills[1];

    $cashSession = $this->cashSessionService->openSession(
        $this->company->id,
        $this->branch->id,
        $this->user->id,
        50000.00
    );

    // Pagar bill1 completamente
    $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000.00,
        idempotencyKey: Str::uuid()->toString(),
        bill: $bill1,
        cashSession: $cashSession,
        userId: $this->user->id
    );

    // Pagar bill2 completamente
    $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000.00,
        idempotencyKey: Str::uuid()->toString(),
        bill: $bill2,
        cashSession: $cashSession,
        userId: $this->user->id
    );

    // Ambas bills deben estar PAID
    $bill1->refresh();
    $bill2->refresh();

    expect($bill1->status)->toBe(BillStatus::PAID)
        ->and($bill2->status)->toBe(BillStatus::PAID);

    // Order debe estar PAID
    $order->refresh();
    expect($order->status)->toBe(OrderStatus::PAID);
});

test('split bill: criterio de cierre - múltiples bills por orden con pagos específicos', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SPLIT-CRITERIA',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'subtotal_gross' => 30000,
        'net_amount' => 25210,
        'tax_amount' => 4790,
        'amount_due' => 30000,
        'subtotal' => 30000,
        'total' => 30000,
    ]);

    // Dividir en 3 partes iguales ($10,000 cada una)
    $bills = $this->billingService->splitEqual($order, 3);
    expect($bills)->toHaveCount(3);

    $cashSession = $this->cashSessionService->openSession(
        $this->company->id,
        $this->branch->id,
        $this->user->id,
        50000.00
    );

    // Pagar cada bill con diferentes montos
    foreach ($bills as $bill) {
        $this->paymentService->registerPayment(
            order: $order,
            paymentMethod: $this->cashMethod,
            amount: 10000.00,
            idempotencyKey: Str::uuid()->toString(),
            bill: $bill,
            cashSession: $cashSession,
            userId: $this->user->id
        );
    }

    // Verificar criterio de cierre:
    // "Es posible tener varias Bills de una misma Order y saber
    //  exactamente qué Payment pertenece a cada Bill"
    
    $allPayments = Payment::where('order_id', $order->id)->get();
    expect($allPayments)->toHaveCount(3);

    foreach ($bills as $bill) {
        $bill->refresh();
        $paymentsForBill = Payment::where('bill_id', $bill->id)->get();
        
        expect($paymentsForBill)->toHaveCount(1)
            ->and($bill->status)->toBe(BillStatus::PAID)
            ->and((float) $bill->paid_amount)->toBe(10000.00);
    }

    // Verificar que cada payment tiene bill_id inequívoco
    foreach ($allPayments as $payment) {
        expect($payment->bill_id)->not->toBeNull();
    }
});
