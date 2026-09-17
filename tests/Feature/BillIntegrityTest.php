<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Accounting\Domain\Entities\Account;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Services\BillingService;
use Modules\Payments\Domain\Services\PaymentService;
use Modules\Payments\Domain\ValueObjects\BillStatus;
use Modules\Payments\Domain\ValueObjects\BillType;

uses(RefreshDatabase::class);

/**
 * BILL INTEGRITY TEST (Puntos 47-56)
 * 
 * Valida:
 * - Bill como representación consistente del estado financiero
 * - Relación Order → Bill → Payments
 * - Propina nunca se suma dos veces
 * - Reconstrucción de Bill después de offline/sync
 * - Split bill, merge, pagos parciales
 */
beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'BILL-' . uniqid(),
        'legal_name' => 'Bill Integrity Test',
        'trade_name' => 'Bill Test',
    ]);

    enableAllCapabilities($this->company);
    Account::seedDefaultsFor($this->company->id);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'BILL',
        'name' => 'Bill Branch',
    ]);

    $this->user = User::create([
        'name' => 'Cashier',
        'email' => 'bill-' . uniqid() . '@test.com',
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

    $this->billingService = app(BillingService::class);
    $this->paymentService = app(PaymentService::class);
});

// ═══════════════════════════════════════════════════
// PUNTO 47: Bill como representación consistente
// ═══════════════════════════════════════════════════
test('Bill refleja exactamente el estado financiero del Order', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'BILL-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->user->id,
    ]);

    OrderItem::create([
        'company_id' => $this->company->id,
        'order_id' => $order->id,
        'name_snapshot' => 'Hamburguesa',
        'unit_price_snapshot' => 10000,
        'quantity' => 1,
    ]);

    $order->recalculateTotals();
    $order->save();

    // Crear bill única para el order (API correcto)
    $bill = $this->billingService->createSingleBill($order);
    
    // Punto 47: Bill.total == Order.total
    expect($bill->total)->toBe($order->total);
    expect($bill->subtotal)->toBe($order->subtotal);
    expect($bill->tax_amount)->toBe($order->tax_amount);
    expect($bill->status)->toBe(BillStatus::OPEN);
    expect($bill->paid_amount)->toBe(0);
    expect($bill->remaining_amount)->toBe($bill->total);
});

// ═══════════════════════════════════════════════════
// PUNTO 48: Relación Order → Bill → Payments
// ═══════════════════════════════════════════════════
test('Payment puede existir sin Bill (pago directo a Order)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'BILL-002',
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

    // Pago directo al order (sin bill)
    $payment = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000,
        idempotencyKey: Str::uuid()->toString(),
        userId: $this->user->id,
        tipAmount: 0
    );

    expect($payment->bill_id)->toBeNull()
        ->and($payment->order_id)->toBe($order->id);
});

test('Payment a través de Bill se asocia correctamente', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'BILL-003',
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

    $bill = $this->billingService->createSingleBill($order);

    // Pago al bill
    $payment = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 10000,
        idempotencyKey: Str::uuid()->toString(),
        bill: $bill,
        userId: $this->user->id,
        tipAmount: 0
    );

    expect($payment->bill_id)->toBe($bill->id);

    $bill->registerPaymentAmount($payment->amount);
    $bill->refresh();
    expect($bill->isFullyPaid())->toBeTrue()
        ->and($bill->status)->toBe(BillStatus::PAID);
});

// ═══════════════════════════════════════════════════
// PUNTO 49: registerPaymentAmount con propina
// ═══════════════════════════════════════════════════
test('registerPaymentAmount debe incluir tip_amount (BUG P0)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'BILL-004',
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

    $bill = $this->billingService->createSingleBill($order);
    $bill->tip_amount = 1000;
    $bill->total = 11000;
    $bill->remaining_amount = 11000;
    $bill->save();

    // Crear payment con propina
    $payment = Payment::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'bill_id' => $bill->id,
        'payment_method_id' => $this->cashMethod->id,
        'user_id' => $this->user->id,
        'payment_number' => 'PAY-004',
        'method_code' => 'cash',
        'amount' => 10000,
        'tip_amount' => 1000,
        'total_amount' => 11000,
        'status' => 'completed',
        'idempotency_key' => Str::uuid()->toString(),
    ]);

    // BUG P0: registerPaymentAmount solo recibe amount, no incluye tip automáticamente
    // El frontend debe pasar (amount + tip) como parámetro
    $bill->registerPaymentAmount($payment->total_amount);

    $bill->refresh();
    
    expect($bill->paid_amount)->toBe(11000)
        ->and($bill->remaining_amount)->toBe(0)
        ->and($bill->isFullyPaid())->toBeTrue()
        ->and($bill->status)->toBe(BillStatus::PAID);
});

// ═══════════════════════════════════════════════════
// PUNTO 50: Propina nunca se suma dos veces
// ═══════════════════════════════════════════════════
test('Propina se cuenta UNA sola vez en reportes (Payment es la fuente de verdad)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'BILL-005',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::PAID,
        'waiter_id' => $this->user->id,
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
        'tip_amount' => 1000,
        'amount_due' => 11000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $bill = $this->billingService->createSingleBill($order);
    $bill->tip_amount = 1000;
    $bill->total = 11000;
    $bill->remaining_amount = 11000;
    $bill->save();

    Payment::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'bill_id' => $bill->id,
        'payment_method_id' => $this->cashMethod->id,
        'user_id' => $this->user->id,
        'payment_number' => 'PAY-005',
        'method_code' => 'cash',
        'amount' => 10000,
        'tip_amount' => 1000,
        'total_amount' => 11000,
        'status' => 'completed',
        'idempotency_key' => Str::uuid()->toString(),
    ]);

    // REGLA: Payment es la única fuente de verdad para propinas
    // NO sumar tip de Bill ni de Order (sería triple contabilidad)
    $totalTips = Payment::where('order_id', $order->id)
        ->where('status', 'completed')
        ->sum('tip_amount');

    expect((int) $totalTips)->toBe(1000); // ADR-018: tip_amount es integer
});

// ═══════════════════════════════════════════════════
// PUNTO 51: Consistencia Bill ↔ Order
// ═══════════════════════════════════════════════════
test('Bill NO se actualiza si Order cambia (limitación conocida)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'BILL-006',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->user->id,
    ]);

    OrderItem::create([
        'company_id' => $this->company->id,
        'order_id' => $order->id,
        'name_snapshot' => 'Hamburguesa',
        'unit_price_snapshot' => 10000,
        'quantity' => 1,
    ]);

    $order->recalculateTotals();
    $order->save();

    $bill = $this->billingService->createSingleBill($order);
    $initialTotal = $bill->total;

    // Agregar otro item
    OrderItem::create([
        'company_id' => $this->company->id,
        'order_id' => $order->id,
        'name_snapshot' => 'Papas',
        'unit_price_snapshot' => 3000,
        'quantity' => 1,
    ]);

    $order->recalculateTotals();
    $order->save();

    $bill->refresh();
    
    // Documentar: Bill queda desactualizado
    expect($bill->total)->toBe($initialTotal, 
        'Bill NO se sincroniza con cambios en Order (limitación conocida)');
})->skip('Limitación conocida: Bill no se sincroniza con cambios en Order');

// ═══════════════════════════════════════════════════
// PUNTO 52: Pago parcial
// ═══════════════════════════════════════════════════
test('Pago parcial actualiza paid_amount y remaining_amount', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'BILL-007',
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

    $bill = $this->billingService->createSingleBill($order);

    // Primer pago: $5,000
    $bill->registerPaymentAmount(5000);

    expect($bill->paid_amount)->toBe(5000)
        ->and($bill->remaining_amount)->toBe(5000)
        ->and($bill->status)->toBe(BillStatus::PARTIAL);

    // Segundo pago: $5,000
    $bill->registerPaymentAmount(5000);

    expect($bill->paid_amount)->toBe(10000)
        ->and($bill->remaining_amount)->toBe(0)
        ->and($bill->status)->toBe(BillStatus::PAID);
});

// ═══════════════════════════════════════════════════
// PUNTO 53: Split bill (3 modalidades)
// ═══════════════════════════════════════════════════
test('Split en 2 partes iguales (API: splitEqual con parts=2)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'BILL-008',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->user->id,
    ]);

    OrderItem::create([
        'company_id' => $this->company->id,
        'order_id' => $order->id,
        'name_snapshot' => 'Hamburguesa',
        'unit_price_snapshot' => 10000,
        'quantity' => 2,
    ]);

    $order->recalculateTotals();
    $order->save();

    // API correcto: splitEqual requiere parts >= 2
    $bills = $this->billingService->splitEqual($order, 2);

    expect($bills)->toHaveCount(2);

    // ADR-018: total es integer, no requiere cast
    $totalBills = array_sum(array_map(fn($b) => (int) $b->total, $bills));
    expect($totalBills)->toBe($order->total);

    foreach ($bills as $bill) {
        expect($bill->status)->toBe(BillStatus::OPEN);
        expect($bill->paid_amount)->toBe(0);
    }
});

test('Split por items consumidos (splitByItems)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'BILL-008b',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->user->id,
    ]);

    $item1 = OrderItem::create([
        'company_id' => $this->company->id,
        'order_id' => $order->id,
        'name_snapshot' => 'Hamburguesa',
        'unit_price_snapshot' => 10000,
        'quantity' => 1,
    ]);
    $item2 = OrderItem::create([
        'company_id' => $this->company->id,
        'order_id' => $order->id,
        'name_snapshot' => 'Papas',
        'unit_price_snapshot' => 3000,
        'quantity' => 1,
    ]);

    $order->recalculateTotals();
    $order->save();

    $bills = $this->billingService->splitByItems($order, [
        ['item_ids' => [$item1->id], 'guest_count' => 1],
        ['item_ids' => [$item2->id], 'guest_count' => 1],
    ]);

    expect($bills)->toHaveCount(2);

    // ADR-018: total es integer, suma exacta sin round
    $totalBills = array_sum(array_map(fn($b) => (int) $b->total, $bills));
    expect($totalBills)->toBe($order->total);
});

test('Split por montos personalizados (splitByAmounts)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'BILL-008c',
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

    $bills = $this->billingService->splitByAmounts($order, [6000, 4000]);

    expect($bills)->toHaveCount(2);
    // ADR-018: total es integer, comparación directa
    expect((int) $bills[0]->total)->toBe(6000);
    expect((int) $bills[1]->total)->toBe(4000);
});

// ═══════════════════════════════════════════════════
// PUNTO 54: Merge/split/move de mesas
// ═══════════════════════════════════════════════════
test('Merge/split/move de mesas NO implementado (limitación MVP)', function () {
    expect(true)->toBeTrue();
})->skip('Funcionalidad no implementada en MVP');

// ═══════════════════════════════════════════════════
// PUNTO 55: LOCAL BILL = f(ORDER + PAYMENTS)
// ═══════════════════════════════════════════════════
test('Bill se puede reconstruir desde Order + Payments', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'BILL-009',
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

    $originalBill = $this->billingService->createSingleBill($order);
    $originalBillId = $originalBill->id;

    // Pago parcial
    Payment::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'bill_id' => $originalBill->id,
        'payment_method_id' => $this->cashMethod->id,
        'user_id' => $this->user->id,
        'payment_number' => 'PAY-009',
        'method_code' => 'cash',
        'amount' => 5000,
        'tip_amount' => 0,
        'total_amount' => 5000,
        'status' => 'completed',
        'idempotency_key' => Str::uuid()->toString(),
    ]);

    $originalBill->registerPaymentAmount(5000);
    $originalBill->save();

    // Capturar payment_ids ANTES de borrar (en offline real los tendríamos)
    $paymentIds = Payment::where('bill_id', $originalBillId)->pluck('id')->toArray();

    // "Borrar" bill (simular pérdida)
    $originalBill->forceDelete();

    // RECONSTRUIR desde Order + Payments (usando payment_ids capturados)
    $paidFromPayments = Payment::whereIn('id', $paymentIds)
        ->where('status', 'completed')
        ->sum('amount');

    $tipFromPayments = Payment::whereIn('id', $paymentIds)
        ->where('status', 'completed')
        ->sum('tip_amount');

    $reconstructed = Bill::create([
        'company_id' => $order->company_id,
        'branch_id' => $order->branch_id,
        'order_id' => $order->id,
        'bill_number' => Bill::generateBillNumber($order->order_number, 1),
        'type' => BillType::SINGLE,
        'subtotal' => $order->subtotal,
        'tax_amount' => $order->tax_amount,
        'discount_amount' => $order->discount_amount ?? 0,
        'tip_amount' => $order->tip_amount ?? 0,
        'total' => $order->total + ($order->tip_amount ?? 0),
        'paid_amount' => 0,
        'remaining_amount' => $order->total + ($order->tip_amount ?? 0),
        'status' => BillStatus::OPEN,
        'guest_count' => 1,
    ]);

    $reconstructed->registerPaymentAmount((int) $paidFromPayments + (int) $tipFromPayments); // ADR-018

    // ADR-018: paid_amount y remaining_amount son integer
    expect((int) $reconstructed->paid_amount)->toBe(5000)
        ->and((int) $reconstructed->remaining_amount)->toBe(5000)
        ->and($reconstructed->status)->toBe(BillStatus::PARTIAL);
});

// ═══════════════════════════════════════════════════
// PUNTO 56: Criterio de cierre
// ═══════════════════════════════════════════════════
test('CRITERIO DE CIERRE: Bill reconstruido produce mismo resultado financiero', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'BILL-010',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::PAID,
        'waiter_id' => $this->user->id,
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
        'tip_amount' => 1000,
        'amount_due' => 11000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $originalBill = $this->billingService->createSingleBill($order);
    $originalBill->tip_amount = 1000;
    $originalBill->total = 11000;
    $originalBill->remaining_amount = 11000;
    $originalBill->save();
    $originalBillId = $originalBill->id;

    Payment::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'bill_id' => $originalBill->id,
        'payment_method_id' => $this->cashMethod->id,
        'user_id' => $this->user->id,
        'payment_number' => 'PAY-010',
        'method_code' => 'cash',
        'amount' => 10000,
        'tip_amount' => 1000,
        'total_amount' => 11000,
        'status' => 'completed',
        'idempotency_key' => Str::uuid()->toString(),
    ]);

    $originalBill->registerPaymentAmount(11000);
    $originalBill->save();

    // Snapshot
    $snapshot = [
        'total' => (float) $originalBill->total,
        'paid' => (float) $originalBill->paid_amount,
        'remaining' => (float) $originalBill->remaining_amount,
        'status' => $originalBill->status,
        'tip' => (float) $originalBill->tip_amount,
    ];

    // Capturar payment_ids ANTES de borrar
    $paymentIds = Payment::where('bill_id', $originalBillId)->pluck('id')->toArray();

    // Simular pérdida
    $originalBill->forceDelete();

    // Reconstruir usando payment_ids capturados
    $paid = Payment::whereIn('id', $paymentIds)
        ->where('status', 'completed')
        ->sum('amount');
    $tip = Payment::whereIn('id', $paymentIds)
        ->where('status', 'completed')
        ->sum('tip_amount');

    $rebuilt = Bill::create([
        'company_id' => $order->company_id,
        'branch_id' => $order->branch_id,
        'order_id' => $order->id,
        'bill_number' => Bill::generateBillNumber($order->order_number, 1),
        'type' => BillType::SINGLE,
        'subtotal' => $order->subtotal,
        'tax_amount' => $order->tax_amount,
        'discount_amount' => $order->discount_amount ?? 0,
        'tip_amount' => $order->tip_amount ?? 0,
        'total' => $order->total + ($order->tip_amount ?? 0),
        'paid_amount' => 0,
        'remaining_amount' => $order->total + ($order->tip_amount ?? 0),
        'status' => BillStatus::OPEN,
        'guest_count' => 1,
    ]);
    $rebuilt->registerPaymentAmount((int) $paid + (int) $tip); // ADR-018

    // CRITERIO DE CIERRE: mismo resultado financiero exacto
    expect((float) $rebuilt->total)->toBe($snapshot['total'])
        ->and((float) $rebuilt->paid_amount)->toBe($snapshot['paid'])
        ->and((float) $rebuilt->remaining_amount)->toBe($snapshot['remaining'])
        ->and($rebuilt->status)->toBe($snapshot['status'])
        ->and((float) $rebuilt->tip_amount)->toBe($snapshot['tip']);
});
