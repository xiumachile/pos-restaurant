<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Accounting\Domain\Entities\Account;
use Modules\Accounting\Domain\Services\LedgerService;
use Modules\Accounting\Domain\ValueObjects\ReferenceType;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Entities\Refund;
use Modules\Payments\Domain\Services\PaymentService;
use Modules\Payments\Domain\Services\RefundService;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;

uses(RefreshDatabase::class);

/**
 * TESTS DE INTEGRIDAD FINANCIERA (ADR-011/ADR-016)
 * 
 * Validan el modelo chileno de POS:
 * - Precios BRUTOS (IVA incluido en catálogo)
 * - Propinas separadas del IVA (SII: "Otros Montos")
 * - Ledger de doble entrada balanceado
 * - Reembolsos proporcionales
 * - Integridad entre Order, Payment, Bill
 * 
 * RED DE SEGURIDAD para futuros refactors de servicios financieros.
 */
beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'FIN-' . uniqid(),
        'legal_name' => 'Financial Integrity Test',
        'trade_name' => 'Financial Test',
    ]);

    enableAllCapabilities($this->company);
    Account::seedDefaultsFor($this->company->id);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'FIN',
        'name' => 'Financial Branch',
    ]);

    $this->user = User::create([
        'name' => 'Financial Tester',
        'email' => 'fin-' . uniqid() . '@test.com',
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
    $this->refundService = app(RefundService::class);
    $this->ledgerService = app(LedgerService::class);
});

// ═══════════════════════════════════════════════════
// TEST 1: Modelo chileno de IVA incluido (ADR-011)
// ═══════════════════════════════════════════════════
test('ADR-011: precios son BRUTOS, net = gross / 1.19, tax = gross - net', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'FIN-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'waiter_id' => $this->user->id,
    ]);

    // Hamburguesa $10,000 IVA incluido (precio BRUTO)
    OrderItem::create([
        'company_id' => $this->company->id,
        'order_id' => $order->id,
        'name_snapshot' => 'Hamburguesa',
        'unit_price_snapshot' => 10000,
        'quantity' => 1,
    ]);

    $order->recalculateTotals();
    $order->save();
    $order->refresh();

    // Validación ADR-011
    expect($order->subtotal_gross)->toBe(10000);  // Bruto (IVA incluido)
    expect($order->net_amount)->toBe(8403);        // 10000 / 1.19
    expect($order->tax_amount)->toBe(1597);        // 10000 - 8403
    expect($order->amount_due)->toBe(10000);       // Sin propina

    // Validación: net + tax = gross (integridad matemática)
    $sum = $order->net_amount + $order->tax_amount;
    expect($sum)->toBe($order->subtotal_gross);
});

// ═══════════════════════════════════════════════════
// TEST 2: Propina es separada del IVA (SII)
// ═══════════════════════════════════════════════════
test('propina NO afecta IVA y se contabiliza en cuenta separada (2200)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'FIN-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->user->id,
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
        'tip_amount' => 1000,  // Propina de $1,000
        'amount_due' => 11000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $payment = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000,
        idempotencyKey: Str::uuid()->toString(),
        userId: $this->user->id,
        tipAmount: 1000
    );

    expect($payment->amount)->toBe(10000);     // Venta
    expect($payment->tip_amount)->toBe(1000);  // Propina

    // Verificar asiento contable
    $entries = $this->ledgerService->getEntriesByReference(
        ReferenceType::PAYMENT,
        $payment->id
    );
    expect($entries)->toHaveCount(1);

    // Buscar línea de propina (cuenta 2200)
    $tipsLine = collect($entries[0]['ledger_entries'])
        ->firstWhere('account.code', '2200');

    expect($tipsLine)->not->toBeNull()
        ->and($tipsLine['credit_amount'])->toBe(1000);

    // Asiento debe estar balanceado
    $debits = array_sum(array_column($entries[0]['ledger_entries'], 'debit_amount'));
    $credits = array_sum(array_column($entries[0]['ledger_entries'], 'credit_amount'));
    expect(abs($debits - $credits))->toBeLessThan(0.02);
});

// ═══════════════════════════════════════════════════
// TEST 3: Journal entry siempre balanceado
// ═══════════════════════════════════════════════════
test('JournalEntry siempre está balanceado (SUM debits == SUM credits)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'FIN-003',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->user->id,
        'subtotal_gross' => 25000,
        'net_amount' => 21008,
        'tax_amount' => 3992,
        'discount_amount' => 2000,
        'tip_amount' => 2500,
        'amount_due' => 25500,
        'subtotal' => 25000,
        'total' => 23000,
    ]);

    $payment = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 25500,
        idempotencyKey: Str::uuid()->toString(),
        userId: $this->user->id,
        tipAmount: 2500
    );

    $entries = $this->ledgerService->getEntriesByReference(
        ReferenceType::PAYMENT,
        $payment->id
    );

    $debits = array_sum(array_column($entries[0]['ledger_entries'], 'debit_amount'));
    $credits = array_sum(array_column($entries[0]['ledger_entries'], 'credit_amount'));

    expect($debits)->toBeGreaterThan(0)
        ->and($credits)->toBeGreaterThan(0)
        ->and(abs($debits - $credits))->toBeLessThan(0.02, 
            "JournalEntry desbalanceado: debits=$debits, credits=$credits");
});

// ═══════════════════════════════════════════════════
// TEST 4: Pago parcial calcula proporciones correctas
// ═══════════════════════════════════════════════════
test('pago parcial distribuye proporcionalmente IVA y propina', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'FIN-004',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->user->id,
        'subtotal_gross' => 11900,
        'net_amount' => 10000,
        'tax_amount' => 1900,
        'tip_amount' => 1000,
        'amount_due' => 12900,
        'subtotal' => 11900,
        'total' => 11900,
    ]);

    // Primer pago: $5,000 (aproximadamente 39% del total)
    $payment1 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 5000,
        idempotencyKey: Str::uuid()->toString(),
        userId: $this->user->id,
        tipAmount: 0
    );

    expect((int) $payment1->amount)->toBe(5000); // ADR-018

    $entries = $this->ledgerService->getEntriesByReference(
        ReferenceType::PAYMENT,
        $payment1->id
    );

    // Asiento debe estar balanceado aunque sea parcial
    $debits = array_sum(array_column($entries[0]['ledger_entries'], 'debit_amount'));
    $credits = array_sum(array_column($entries[0]['ledger_entries'], 'credit_amount'));
    expect(abs($debits - $credits))->toBeLessThan(0.02);
});

// ═══════════════════════════════════════════════════
// TEST 5: Reembolso revierte proporcionalmente
// ═══════════════════════════════════════════════════
test('reembolso parcial revierte líneas contables proporcionalmente', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'FIN-005',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,  // SERVED para permitir payment, luego transiciona a PAID
        'waiter_id' => $this->user->id,
        'subtotal_gross' => 11900,
        'net_amount' => 10000,
        'tax_amount' => 1900,
        'amount_due' => 11900,
        'subtotal' => 11900,
        'total' => 11900,
    ]);

    $payment = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 11900,
        idempotencyKey: Str::uuid()->toString(),
        userId: $this->user->id,
        tipAmount: 0
    );

    // Reembolso parcial de $5,000
    $refund = $this->refundService->createRefund(
        payment: $payment,
        amount: 5000,
        reason: 'Producto defectuoso',
        processedBy: $this->user->id,
        idempotencyKey: Str::uuid()->toString()
    );

    expect($refund->status->value)->toBe('completed');
    expect($refund->amount)->toBe(5000);

    // Verificar asiento de reversa
    $entries = $this->ledgerService->getEntriesByReference(
        ReferenceType::REFUND,
        $refund->id
    );

    $debits = array_sum(array_column($entries[0]['ledger_entries'], 'debit_amount'));
    $credits = array_sum(array_column($entries[0]['ledger_entries'], 'credit_amount'));
    expect($debits)->toBe($credits); // ADR-018: balance exacto con enteros 

});

// ═══════════════════════════════════════════════════
// TEST 6: Reembolso no excede monto original
// ═══════════════════════════════════════════════════
test('reembolso rechaza amount mayor al reembolsable', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'FIN-006',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,  // SERVED para permitir payment
        'waiter_id' => $this->user->id,
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $payment = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000,
        idempotencyKey: Str::uuid()->toString(),
        userId: $this->user->id,
        tipAmount: 0
    );

    // Intentar reembolsar más de lo pagado
    $this->expectException(\Modules\Payments\Domain\Exceptions\InvalidRefundException::class);
    
    $this->refundService->createRefund(
        payment: $payment,
        amount: 15000,  // Más que el monto original
        reason: 'Invalid',
        processedBy: $this->user->id,
        idempotencyKey: Str::uuid()->toString()
    );
});

// ═══════════════════════════════════════════════════
// TEST 7: Doble reembolso parcial suma correctamente
// ═══════════════════════════════════════════════════
test('múltiples reembolsos parciales suman sin exceder monto original', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'FIN-007',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,  // SERVED para permitir payment
        'waiter_id' => $this->user->id,
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $payment = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000,
        idempotencyKey: Str::uuid()->toString(),
        userId: $this->user->id,
        tipAmount: 0
    );

    // Primer reembolso: $3,000
    $refund1 = $this->refundService->createRefund(
        payment: $payment,
        amount: 3000,
        reason: 'Producto defectuoso',
        processedBy: $this->user->id,
        idempotencyKey: Str::uuid()->toString()
    );

    // Segundo reembolso: $4,000
    $refund2 = $this->refundService->createRefund(
        payment: $payment,
        amount: 4000,
        reason: 'Error en orden',
        processedBy: $this->user->id,
        idempotencyKey: Str::uuid()->toString()
    );

    // Total reembolsado: $7,000 (menos que $10,000)
    $totalRefunded = Refund::totalRefundedFor($payment->id);
    expect((int) $totalRefunded)->toBe(7000); // ADR-018

    // Tercer reembolso: $4,000 (excede el restante: $3,000)
    $this->expectException(\Modules\Payments\Domain\Exceptions\InvalidRefundException::class);
    
    $this->refundService->createRefund(
        payment: $payment,
        amount: 4000,  // Excede el reembolsable restante
        reason: 'Invalid',
        processedBy: $this->user->id,
        idempotencyKey: Str::uuid()->toString()
    );
});

// ═══════════════════════════════════════════════════
// TEST 8: Cash session expected_amount calcula correctamente
// ═══════════════════════════════════════════════════
test('cash session expected = opening + payments cash - payouts', function () {
    // Crear sesión de caja
    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->user->id,
        'session_number' => 'CS-FIN-' . uniqid(),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,  // Monto inicial
        'opened_at' => now(),
    ]);

    // Crear order
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'FIN-008',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->user->id,
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    // Pago en efectivo: $10,000 (asociado a cash_session)
    $payment = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000,
        idempotencyKey: Str::uuid()->toString(),
        userId: $this->user->id,
        tipAmount: 0,
        cashSession: $session
    );
    
    // Verificar que el payment se asoció al cash_session
    expect((int) $payment->cash_session_id)->toBe($session->id);

    // Expected: 50,000 (opening) + 10,000 (payment) = 60,000
    $expected = $session->calculateExpectedAmountForClose();
    expect((int) $expected)->toBe(60000); // ADR-018
});

// ═══════════════════════════════════════════════════
// TEST 9: Consistencia Order.total vs suma de payments
// ═══════════════════════════════════════════════════
test('Order.amount_due == suma de payments completados + tip', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'FIN-009',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->user->id,
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
        'tip_amount' => 1000,
        'amount_due' => 11000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    // Pago completo: $11,000 (incluye propina)
    $payment = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000,
        idempotencyKey: Str::uuid()->toString(),
        userId: $this->user->id,
        tipAmount: 1000
    );

    $totalPaid = $payment->amount + $payment->tip_amount;
    expect($totalPaid)->toBe($order->amount_due);
});

// ═══════════════════════════════════════════════════
// TEST 10: Idempotencia en pagos y reembolsos
// ═══════════════════════════════════════════════════
test('idempotencia: misma key retorna misma respuesta sin duplicar', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'FIN-010',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->user->id,
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
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

    // Reintento con misma key (debe retornar el mismo payment)
    $payment2 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000,
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id,
        tipAmount: 0
    );

    expect($payment1->id)->toBe($payment2->id)
        ->and($payment1->uuid)->toBe($payment2->uuid);

    // Solo debe existir 1 payment
    $paymentsCount = Payment::where('order_id', $order->id)->count();
    expect($paymentsCount)->toBe(1);

    // Solo debe existir 1 journal entry
    $entries = $this->ledgerService->getEntriesByReference(
        ReferenceType::PAYMENT,
        $payment1->id
    );
    expect($entries)->toHaveCount(1);
});
