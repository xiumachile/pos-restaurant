<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Domain\Entities\Account;
use Modules\Accounting\Domain\Services\LedgerService;
use Modules\Accounting\Domain\ValueObjects\AccountType;
use Modules\Accounting\Domain\ValueObjects\ReferenceType;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Entities\Refund;
use Modules\Payments\Domain\Exceptions\InvalidRefundException;
use Modules\Payments\Domain\Services\RefundService;
use Modules\Payments\Domain\ValueObjects\PaymentStatus;
use Modules\Payments\Domain\ValueObjects\RefundStatus;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'REFUND-' . uniqid(),
        'legal_name' => 'Refund Test Company',
        'trade_name' => 'Refund Test',
    ]);

    enableAllCapabilities($this->company);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'REF',
        'name' => 'Refund Branch',
    ]);

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'refund-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'admin',
    ]);

    // Crear método de pago (NOT NULL en payments)
    $this->cashMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'cash',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    // Crear order (NOT NULL en payments)
    $this->order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-REFUND-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::PAID,
        'waiter_id' => $this->user->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    // Crear cuentas contables necesarias
    $this->cashAccount = Account::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => '1100',
        'name' => 'Efectivo',
        'type' => AccountType::ASSET,
    ]);

    $this->revenueAccount = Account::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => '4100',
        'name' => 'Ingresos',
        'type' => AccountType::REVENUE,
    ]);

    $this->taxAccount = Account::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => '2100',
        'name' => 'IVA por Pagar',
        'type' => AccountType::LIABILITY,
    ]);

    $this->ledgerService = app(LedgerService::class);
    $this->refundService = app(RefundService::class);

    // Crear pago de prueba CON TODOS LOS CAMPOS OBLIGATORIOS
    $this->payment = Payment::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $this->order->id,
        'payment_method_id' => $this->cashMethod->id,
        'user_id' => $this->user->id,
        'payment_number' => 'PAY-TEST-' . uniqid(),
        'method_code' => 'cash',
        'amount' => 10000,
        'tip_amount' => 0,
        'total_amount' => 11900,
        'status' => PaymentStatus::COMPLETED,
        'paid_at' => now(),
        'idempotency_key' => \Illuminate\Support\Str::uuid()->toString(),
    ]);

    // Crear asiento contable del payment
    $this->ledgerService->createJournalEntry(
        $this->company->id,
        $this->branch->id,
        ReferenceType::PAYMENT,
        $this->payment->id,
        [
            ['account_id' => $this->cashAccount->id, 'debit' => 11900, 'credit' => 0],
            ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 10000],
            ['account_id' => $this->taxAccount->id, 'debit' => 0, 'credit' => 1900],
        ],
        'Pago de prueba'
    );
});

test('crea refund completo (100%)', function () {
    $refund = $this->refundService->createRefund(
        $this->payment,
        11900,
        'Cliente solicitó reembolso',
        $this->user->id,
        \Illuminate\Support\Str::uuid()->toString()
    );

    expect($refund)->toBeInstanceOf(Refund::class)
        ->and($refund->status)->toBe(RefundStatus::COMPLETED)
        ->and((float) $refund->amount)->toBe(11900.0)
        ->and($refund->journal_entry_id)->not->toBeNull()
        ->and($refund->processed_at)->not->toBeNull();

    // Verificar asiento de reversa
    $je = $refund->journalEntry;
    expect($je->isBalanced())->toBeTrue()
        ->and($je->reference_type)->toBe(ReferenceType::REFUND)
        ->and($je->reference_id)->toBe($refund->id);

    // Verificar que las cuentas se revirtieron
    $cashBalance = $this->ledgerService->getAccountBalance($this->cashAccount->id);
    $revenueBalance = $this->ledgerService->getAccountBalance($this->revenueAccount->id);

    expect($cashBalance)->toBe(0.0)
        ->and($revenueBalance)->toBe(0.0);
});

test('crea refund parcial (50%)', function () {
    $refund = $this->refundService->createRefund(
        $this->payment,
        5000,
        'Devolución parcial',
        $this->user->id
    );

    expect($refund->status)->toBe(RefundStatus::COMPLETED)
        ->and((float) $refund->amount)->toBe(5000.0);

    // Balance de efectivo: 11900 - 5000 = 6900
    $cashBalance = $this->ledgerService->getAccountBalance($this->cashAccount->id);
    expect($cashBalance)->toBe(6900.0);
});

test('rechaza refund que excede monto del payment', function () {
    $this->refundService->createRefund($this->payment, 20000);
})->throws(InvalidRefundException::class);

test('rechaza refund cuando suma de refunds previos excede reembolsable', function () {
    $this->refundService->createRefund($this->payment, 8000);
    $this->refundService->createRefund($this->payment, 5000);
})->throws(InvalidRefundException::class);

test('rechaza refund con amount negativo', function () {
    $this->refundService->createRefund($this->payment, -100);
})->throws(InvalidRefundException::class);

test('idempotencia: mismo idempotency_key retorna refund existente', function () {
    $key = \Illuminate\Support\Str::uuid()->toString();

    $refund1 = $this->refundService->createRefund($this->payment, 1000, null, null, $key);
    $refund2 = $this->refundService->createRefund($this->payment, 1000, null, null, $key);

    expect($refund1->id)->toBe($refund2->id);

    $totalRefunded = Refund::totalRefundedFor($this->payment->id);
    expect($totalRefunded)->toBe(1000.0);
});

test('obtiene refunds de un payment', function () {
    $this->refundService->createRefund($this->payment, 1000);
    $this->refundService->createRefund($this->payment, 2000);

    $refunds = $this->refundService->getRefundsForPayment($this->payment->id);

    expect($refunds)->toHaveCount(2);
});

test('calcula total reembolsado correctamente', function () {
    $this->refundService->createRefund($this->payment, 1000);
    $this->refundService->createRefund($this->payment, 2000);
    $this->refundService->createRefund($this->payment, 500);

    $total = Refund::totalRefundedFor($this->payment->id);

    expect($total)->toBe(3500.0);
});

test('refund parcial permite refund adicional hasta el máximo', function () {
    $this->refundService->createRefund($this->payment, 5000);

    // Quedan 6900 reembolsables
    $refund2 = $this->refundService->createRefund($this->payment, 6900);

    expect($refund2->status)->toBe(RefundStatus::COMPLETED);

    $total = Refund::totalRefundedFor($this->payment->id);
    expect($total)->toBe(11900.0);
});
