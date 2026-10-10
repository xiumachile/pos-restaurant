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
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Modules\Payments\Domain\Services\PaymentService;
use Modules\Payments\Domain\Exceptions\PaymentException;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Company A
    $this->companyA = Company::create([
        'tax_id' => 'TENANT-A-' . uniqid(),
        'legal_name' => 'Company A',
        'trade_name' => 'Company A',
    ]);

    $this->branchA = Branch::create([
        'company_id' => $this->companyA->id,
        'name' => 'Branch A',
        'code' => 'BA',
    ]);

    // Company B (diferente tenant)
    $this->companyB = Company::create([
        'tax_id' => 'TENANT-B-' . uniqid(),
        'legal_name' => 'Company B',
        'trade_name' => 'Company B',
    ]);

    $this->branchB = Branch::create([
        'company_id' => $this->companyB->id,
        'name' => 'Branch B',
        'code' => 'BB',
    ]);

    $this->userA = User::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'name' => 'User A',
        'email' => 'user-a-' . uniqid() . '@test.com',
        'password' => bcrypt('password'),
        'role' => 'cashier',
    ]);

    $this->paymentMethodA = PaymentMethod::create([
        'company_id' => $this->companyA->id,
        'branch_id' => null,
        'name_translations' => ['es' => 'Efectivo', 'en' => 'Cash'],
        'code' => 'cash',
        'type' => 'cash',
        'is_active' => true,
    ]);

    $this->paymentMethodB = PaymentMethod::create([
        'company_id' => $this->companyB->id,
        'branch_id' => null,
        'name_translations' => ['es' => 'Efectivo', 'en' => 'Cash'],
        'code' => 'cash',
        'type' => 'cash',
        'is_active' => true,
    ]);

    $this->paymentService = app(PaymentService::class);
});

test('Hallazgo 07: PaymentService rechaza PaymentMethod de otro tenant', function () {
    $order = Order::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'waiter_id' => $this->userA->id,
        'order_number' => 'ORD-TENANT-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 10000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 10000,
    ]);

    expect(fn() => $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->paymentMethodB, // ← Diferente tenant
        amount: 10000,
        idempotencyKey: Str::uuid()->toString(),
        userId: $this->userA->id
    ))->toThrow(PaymentException::class, 'tenant');
});

test('Hallazgo 07: PaymentService rechaza CashSession de otro tenant', function () {
    $order = Order::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'waiter_id' => $this->userA->id,
        'order_number' => 'ORD-SESSION-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 10000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 10000,
    ]);

    $cashSessionB = CashSession::create([
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'user_id' => $this->userA->id,
        'session_number' => 'CS-' . uniqid(),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    expect(fn() => $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->paymentMethodA,
        amount: 10000,
        idempotencyKey: Str::uuid()->toString(),
        cashSession: $cashSessionB, // ← Diferente tenant
        userId: $this->userA->id
    ))->toThrow(PaymentException::class, 'tenant');
});

test('Hallazgo 07: PaymentService rechaza CashSession cerrada', function () {
    $order = Order::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'waiter_id' => $this->userA->id,
        'order_number' => 'ORD-CLOSED-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 10000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 10000,
    ]);

    $closedSession = CashSession::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->userA->id,
        'session_number' => 'CS-CLOSED-' . uniqid(),
        'status' => CashSessionStatus::CLOSED,
        'opening_amount' => 50000,
        'closing_amount' => 60000,
        'opened_at' => now()->subHour(),
        'closed_at' => now(),
    ]);

    expect(fn() => $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->paymentMethodA,
        amount: 10000,
        idempotencyKey: Str::uuid()->toString(),
        cashSession: $closedSession,
        userId: $this->userA->id
    ))->toThrow(PaymentException::class, 'abierta');
});

test('Hallazgo 07: PaymentService acepta entidades del mismo tenant', function () {
    $order = Order::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'waiter_id' => $this->userA->id,
        'order_number' => 'ORD-VALID-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 10000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 10000,
    ]);

    $openSession = CashSession::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->userA->id,
        'session_number' => 'CS-VALID-' . uniqid(),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    // ✅ Pasar userId correcto (era 0 por default → violaba FK)
    $payment = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->paymentMethodA,
        amount: 10000,
        idempotencyKey: Str::uuid()->toString(),
        cashSession: $openSession,
        userId: $this->userA->id  // ← FIX: pasar userId válido
    );

    expect($payment)->toBeInstanceOf(\Modules\Payments\Domain\Entities\Payment::class)
        ->and($payment->amount)->toBe(10000)
        ->and($payment->company_id)->toBe($this->companyA->id)
        ->and($payment->user_id)->toBe($this->userA->id);
});
