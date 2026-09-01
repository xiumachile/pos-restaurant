<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Accounting\Domain\Entities\Account;
use Modules\Accounting\Domain\Entities\JournalEntry;
use Modules\Accounting\Domain\Services\LedgerService;
use Modules\Accounting\Domain\ValueObjects\AccountType;
use Modules\Accounting\Domain\ValueObjects\ReferenceType;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Services\PaymentService;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'PAYLED-' . uniqid(),
        'legal_name' => 'Payment Ledger Test',
        'trade_name' => 'PayLedger Test',
    ]);

    enableAllCapabilities($this->company);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'PL',
        'name' => 'PayLedger Branch',
    ]);

    // Sembrar cuentas contables (necesarias para P0-05)
    Account::seedDefaultsFor($this->company->id);

    $this->user = User::create([
        'name' => 'Cashier',
        'email' => 'payled-' . uniqid() . '@test.com',
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

    $this->cardMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'card',
        'name_translations' => ['es' => 'Tarjeta'],
        'type' => 'card',
        'requires_reference' => true,
        'is_active' => true,
    ]);

    $this->paymentService = app(PaymentService::class);
    $this->ledgerService = app(LedgerService::class);

    // Crear order base para los tests
    $this->order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-PAYLED-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->user->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
    ]);
});

test('pago en efectivo genera asiento contable correcto', function () {
    $payment = $this->paymentService->registerPayment(
        order: $this->order,
        paymentMethod: $this->cashMethod,
        amount: 11900,
        idempotencyKey: Str::uuid()->toString(),
        userId: $this->user->id,
        tipAmount: 0
    );

    // Verificar que se creó JournalEntry
    $entries = $this->ledgerService->getEntriesByReference(
        ReferenceType::PAYMENT,
        $payment->id
    );

    expect($entries)->toHaveCount(1);

    $journal = $entries[0];

    // Verificar que está balanceado
    $debits = array_sum(array_column($journal['ledger_entries'], 'debit_amount'));
    $credits = array_sum(array_column($journal['ledger_entries'], 'credit_amount'));
    expect(abs($debits - $credits))->toBeLessThan(0.02);

    // Verificar líneas
    expect($journal['ledger_entries'])->toHaveCount(3); // destination + revenue + tax
});

test('pago con tarjeta usa cuenta 1300 (Por Cobrar Tarjetas)', function () {
    $payment = $this->paymentService->registerPayment(
        order: $this->order,
        paymentMethod: $this->cardMethod,
        amount: 11900,
        idempotencyKey: Str::uuid()->toString(),
        userId: $this->user->id,
        referenceCode: 'AUTH123456'
    );

    $entries = $this->ledgerService->getEntriesByReference(
        ReferenceType::PAYMENT,
        $payment->id
    );

    expect($entries)->toHaveCount(1);

    // Buscar la línea con débito (cuenta destino)
    $destinationLine = collect($entries[0]['ledger_entries'])
        ->firstWhere('debit_amount', '>', 0);

    expect($destinationLine)->not->toBeNull()
        ->and($destinationLine['account']['code'])->toBe('1300'); // Por Cobrar Tarjetas
});

test('pago con propina genera línea en TipsPayable (2200)', function () {
    $payment = $this->paymentService->registerPayment(
        order: $this->order,
        paymentMethod: $this->cashMethod,
        amount: 11900,
        idempotencyKey: Str::uuid()->toString(),
        userId: $this->user->id,
        tipAmount: 2000
    );

    $entries = $this->ledgerService->getEntriesByReference(
        ReferenceType::PAYMENT,
        $payment->id
    );

    expect($entries)->toHaveCount(1);

    // Buscar línea de tips (cuenta 2200 con crédito)
    $tipsLine = collect($entries[0]['ledger_entries'])
        ->firstWhere('account.code', '2200');

    expect($tipsLine)->not->toBeNull()
        ->and((float) $tipsLine['credit_amount'])->toBe(2000.0);
});

test('pago parcial genera asiento proporcional', function () {
    // Primer pago: 5000 de 11900 (ratio = 0.4202)
    $payment = $this->paymentService->registerPayment(
        order: $this->order,
        paymentMethod: $this->cashMethod,
        amount: 5000,
        idempotencyKey: Str::uuid()->toString(),
        userId: $this->user->id,
        tipAmount: 0
    );

    $entries = $this->ledgerService->getEntriesByReference(
        ReferenceType::PAYMENT,
        $payment->id
    );

    expect($entries)->toHaveCount(1);

    // Verificar balance
    $debits = array_sum(array_column($entries[0]['ledger_entries'], 'debit_amount'));
    $credits = array_sum(array_column($entries[0]['ledger_entries'], 'credit_amount'));
    expect(abs($debits - $credits))->toBeLessThan(0.02);

    // Verificar balance de Cash (1100): debe tener 5000 de débito
    $cashAccount = Account::where('company_id', $this->company->id)
        ->where('code', '1100')->first();
    $cashBalance = $this->ledgerService->getAccountBalance($cashAccount->id);
    expect($cashBalance)->toBe(5000.0);
});

test('idempotencia no genera asiento duplicado', function () {
    $key = Str::uuid()->toString();

    $payment1 = $this->paymentService->registerPayment(
        order: $this->order,
        paymentMethod: $this->cashMethod,
        amount: 5000,
        idempotencyKey: $key,
        userId: $this->user->id
    );

    $payment2 = $this->paymentService->registerPayment(
        order: $this->order,
        paymentMethod: $this->cashMethod,
        amount: 5000,
        idempotencyKey: $key,
        userId: $this->user->id
    );

    // Mismo payment retornado
    expect($payment1->id)->toBe($payment2->id);

    // Solo un asiento contable
    $entries = $this->ledgerService->getEntriesByReference(
        ReferenceType::PAYMENT,
        $payment1->id
    );
    expect($entries)->toHaveCount(1);
});

test('múltiples pagos parciales generan asientos independientes', function () {
    // Pago 1: 5000
    $this->paymentService->registerPayment(
        order: $this->order,
        paymentMethod: $this->cashMethod,
        amount: 5000,
        idempotencyKey: Str::uuid()->toString(),
        userId: $this->user->id
    );

    // Pago 2: 4000 (tarjeta)
    $this->paymentService->registerPayment(
        order: $this->order,
        paymentMethod: $this->cardMethod,
        amount: 4000,
        idempotencyKey: Str::uuid()->toString(),
        userId: $this->user->id,
        referenceCode: 'AUTH999'
    );

    // Pago 3: 2900 (efectivo)
    $this->paymentService->registerPayment(
        order: $this->order,
        paymentMethod: $this->cashMethod,
        amount: 2900,
        idempotencyKey: Str::uuid()->toString(),
        userId: $this->user->id
    );

    // Verificar balances finales
    $cashAccount = Account::where('company_id', $this->company->id)
        ->where('code', '1100')->first();
    $cardAccount = Account::where('company_id', $this->company->id)
        ->where('code', '1300')->first();

    $cashBalance = $this->ledgerService->getAccountBalance($cashAccount->id);
    $cardBalance = $this->ledgerService->getAccountBalance($cardAccount->id);

    expect($cashBalance)->toBe(7900.0) // 5000 + 2900
        ->and($cardBalance)->toBe(4000.0);
});

test('cuentas contables se siembran automáticamente si faltan', function () {
    // Eliminar todas las cuentas
    Account::where('company_id', $this->company->id)->delete();

    // Verificar que no existen
    $count = Account::where('company_id', $this->company->id)->count();
    expect($count)->toBe(0);

    // Crear pago - debería sembrar cuentas automáticamente
    $payment = $this->paymentService->registerPayment(
        order: $this->order,
        paymentMethod: $this->cashMethod,
        amount: 11900,
        idempotencyKey: Str::uuid()->toString(),
        userId: $this->user->id
    );

    // Verificar que se crearon las cuentas
    $count = Account::where('company_id', $this->company->id)->count();
    expect($count)->toBeGreaterThan(0);

    // Y que se generó el asiento
    $entries = $this->ledgerService->getEntriesByReference(
        ReferenceType::PAYMENT,
        $payment->id
    );
    expect($entries)->toHaveCount(1);
});
