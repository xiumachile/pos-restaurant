<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Services\BillingService;
use Modules\Payments\Domain\Services\PaymentService;
use Modules\Orders\Domain\ValueObjects\OrderStatus;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create(['tax_id' => 'TEST', 'legal_name' => 'Test Co', 'trade_name' => 'Test']);
    $this->branch = Branch::create(['company_id' => $this->company->id, 'code' => 'T1', 'name' => 'Test Branch']);
    
    $this->user = User::create([
        'name' => 'Cajero',
        'email' => 'cajero@test.test',
        'password' => bcrypt('pw'),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier'
    ]);

    $this->cashMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'CASH',
        'type' => 'cash',
        'name_translations' => ['es' => 'Efectivo', 'zh' => '现金'],
        'is_active' => true,
    ]);

    $this->cardMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'CARD',
        'type' => 'card',
        'name_translations' => ['es' => 'Tarjeta', 'zh' => '卡'],
        'is_active' => true,
    ]);

    $this->paymentService = app(PaymentService::class);
    $this->billingService = app(BillingService::class);
});

/**
 * Helper: Crea un order con items para pruebas.
 * CA-04: El modelo monetario unificado es:
 *   amount_due = grand_total + tip_amount
 *   remaining = amount_due - (sum of payments.amount + sum of payments.tip_amount)
 */
function createMonetaryTestOrder($ctx, int $subtotal, int $tipAmount = 0): Order
{
    $order = Order::create([
        'company_id' => $ctx->company->id,
        'branch_id' => $ctx->branch->id,
        'order_number' => 'ORD-MON-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::SERVED,
        'waiter_id' => $ctx->user->id,
        'subtotal' => $subtotal,
        'tax_amount' => (int) round($subtotal * 0.19),
        'total' => $subtotal + (int) round($subtotal * 0.19),
        'tip_amount' => $tipAmount,
        'amount_due' => $subtotal + (int) round($subtotal * 0.19) + $tipAmount,
    ]);

    OrderItem::create([
        'company_id' => $ctx->company->id,
        'branch_id' => $ctx->branch->id,
        'order_id' => $order->id,
        'name_snapshot' => 'Producto Test',
        'quantity' => 1,
        'unit_price_snapshot' => $subtotal,
        'subtotal' => $subtotal,
        'tax_amount' => (int) round($subtotal * 0.19),
    ]);

    return $order;
}

test('CA-04: Pago parcial con propina mantiene consistencia monetaria', function () {
    $order = createMonetaryTestOrder($this, 10000, 2000);
    // amount_due = 10000 + 1900 (IVA) + 2000 (propina) = 13900

    // Pago parcial de 5000 (venta) + 500 (propina) = 5500 total
    $payment1 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 5000,
        idempotencyKey: \Illuminate\Support\Str::uuid()->toString(),
        userId: $this->user->id,
        tipAmount: 500,
    );

    expect($payment1->amount)->toBe(5000)
        ->and($payment1->tip_amount)->toBe(500)
        ->and($payment1->total_amount)->toBe(5500);

    // Verificar que el order aún no está pagado
    $order->refresh();
    expect($order->status->value)->not->toBe('paid');

    // Verificar que los pagos suman correctamente
    $totalPaid = Payment::where('order_id', $order->id)->completed()->sum('amount');
    $totalTips = Payment::where('order_id', $order->id)->completed()->sum('tip_amount');
    expect($totalPaid)->toBe(5000)
        ->and($totalTips)->toBe(500)
        ->and($totalPaid + $totalTips)->toBe(5500);
});

test('CA-04: Pago total con propina marca el order como pagado', function () {
    $order = createMonetaryTestOrder($this, 10000, 2000);
    // amount_due = 10000 + 1900 + 2000 = 13900

    // Pago completo: 10000 (venta) + 2000 (propina) = 12000
    // Nota: el IVA ya está incluido en el subtotal (ADR-011)
    $payment = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 11900, // total del order (con IVA)
        idempotencyKey: \Illuminate\Support\Str::uuid()->toString(),
        userId: $this->user->id,
        tipAmount: 2000,
    );

    $order->refresh();
    expect($order->status->value)->toBe('paid')
        ->and($order->paid_at)->not->toBeNull();

    // Verificar consistencia: total pagado = amount_due
    $totalPaid = Payment::where('order_id', $order->id)->completed()->sum('amount');
    $totalTips = Payment::where('order_id', $order->id)->completed()->sum('tip_amount');
    expect($totalPaid + $totalTips)->toBe($order->amount_due);
});

test('CA-04: Pago dividido en múltiples métodos con propina', function () {
    $order = createMonetaryTestOrder($this, 10000, 3000);
    // amount_due = 11900 + 3000 = 14900

    // Primer pago: 6000 (venta) + 1000 (propina)
    $payment1 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 6000,
        idempotencyKey: \Illuminate\Support\Str::uuid()->toString(),
        userId: $this->user->id,
        tipAmount: 1000,
    );

    // Segundo pago: 5900 (venta) + 2000 (propina)
    $payment2 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cardMethod,
        amount: 5900,
        idempotencyKey: \Illuminate\Support\Str::uuid()->toString(),
        userId: $this->user->id,
        tipAmount: 2000,
    );

    $order->refresh();
    expect($order->status->value)->toBe('paid');

    // Verificar consistencia total
    $totalPaid = Payment::where('order_id', $order->id)->completed()->sum('amount');
    $totalTips = Payment::where('order_id', $order->id)->completed()->sum('tip_amount');
    expect($totalPaid)->toBe(11900)
        ->and($totalTips)->toBe(3000)
        ->and($totalPaid + $totalTips)->toBe($order->amount_due);
});

test('CA-04: La propina no se suma dos veces (una en bill, otra en payment)', function () {
    $order = createMonetaryTestOrder($this, 10000, 2000);
    // Crear bill única (el servicio de billing no debe incluir la propina en el total de venta)
    $bill = $this->billingService->createSingleBill($order);

    // La bill representa SOLO la venta (sin propina)
    expect($bill->total)->toBe($order->total) // 11900 (venta con IVA)
        ->and($bill->tip_amount)->toBe(0); // La bill no tiene propina asignada

    // La propina se registra en el Payment, no en la Bill
    $payment = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 11900,
        idempotencyKey: \Illuminate\Support\Str::uuid()->toString(),
        bill: $bill,
        userId: $this->user->id,
        tipAmount: 2000,
    );

    expect($payment->amount)->toBe(11900) // Venta
        ->and($payment->tip_amount)->toBe(2000); // Propina separada

    // Verificar que la suma total es correcta: venta + propina = amount_due
    $totalPaid = Payment::where('order_id', $order->id)->completed()->sum('amount');
    $totalTips = Payment::where('order_id', $order->id)->completed()->sum('tip_amount');
    expect($totalPaid + $totalTips)->toBe($order->amount_due);
});

test('CA-04: Idempotencia - reintentar el mismo pago no duplica montos', function () {
    $order = createMonetaryTestOrder($this, 10000, 2000);
    $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();

    // Primer intento
    $payment1 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 11900,
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id,
        tipAmount: 2000,
    );

    // Reintento (simula timeout de red)
    $payment2 = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: 11900,
        idempotencyKey: $idempotencyKey,
        userId: $this->user->id,
        tipAmount: 2000,
    );

    // Debe ser el mismo pago
    expect($payment1->id)->toBe($payment2->id);

    // Verificar que no se duplicaron los montos
    $totalPaid = Payment::where('order_id', $order->id)->completed()->sum('amount');
    $totalTips = Payment::where('order_id', $order->id)->completed()->sum('tip_amount');
    expect($totalPaid)->toBe(11900) // No 23800
        ->and($totalTips)->toBe(2000); // No 4000
});

test('CA-04: El modelo monetario online coincide con el offline (CLP exacto)', function () {
    // Simular el cálculo offline (lo que haría BillRepository + PaymentRepository)
    $subtotal = 10000;
    $tipAmount = 2000;
    $taxAmount = (int) round($subtotal * 0.19); // 1900
    $grandTotal = $subtotal + $taxAmount; // 11900
    $amountDue = $grandTotal + $tipAmount; // 13900

    // Crear order con los mismos datos
    $order = createMonetaryTestOrder($this, $subtotal, $tipAmount);

    // Verificar que el order tiene los mismos cálculos
    expect($order->total)->toBe($grandTotal)
        ->and($order->amount_due)->toBe($amountDue);

    // Pago completo offline (simulado)
    $payment = $this->paymentService->registerPayment(
        order: $order,
        paymentMethod: $this->cashMethod,
        amount: $grandTotal,
        idempotencyKey: \Illuminate\Support\Str::uuid()->toString(),
        userId: $this->user->id,
        tipAmount: $tipAmount,
    );

    // Verificar que el modelo online coincide con el offline
    expect($payment->amount)->toBe($grandTotal)
        ->and($payment->tip_amount)->toBe($tipAmount)
        ->and($payment->total_amount)->toBe($amountDue);

    // Verificar consistencia final
    $order->refresh();
    expect($order->status->value)->toBe('paid');
});
