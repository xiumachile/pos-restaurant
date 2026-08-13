<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\ValueObjects\PaymentMethodType;
use Modules\Payments\Domain\ValueObjects\PaymentStatus;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Modules\Payments\Domain\ValueObjects\BillType;
use Modules\Payments\Domain\ValueObjects\BillStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'PAY-' . uniqid(),
        'legal_name' => 'Payment Test Company',
        'trade_name' => 'Payment Test Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'PAY',
        'name' => 'Payment Branch',
    ]);

    $this->cashier = User::create([
        'name' => 'Test Cashier',
        'email' => 'cashier-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);
});

function createTestMethod($test, string $type = 'cash'): PaymentMethod
{
    return PaymentMethod::create([
        'company_id' => $test->company->id,
        'branch_id' => $test->branch->id,
        'code' => strtoupper($type) . '-' . uniqid(),
        'name_translations' => ['es' => 'Método ' . $type],
        'type' => $type,
        'is_active' => true,
    ]);
}

function createTestOrder($test): Order
{
    return Order::create([
        'company_id' => $test->company->id,
        'branch_id' => $test->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::SERVED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
    ]);
}

// ============================================
// PaymentMethod
// ============================================

test('se puede crear un metodo de pago', function () {
    $method = createTestMethod($this, 'cash');

    expect($method->id)->not->toBeNull();
    expect($method->uuid)->not->toBeNull();
    expect($method->type)->toBe(PaymentMethodType::CASH);
});

test('payment method tipo cash afecta el cajon de efectivo', function () {
    $cash = createTestMethod($this, 'cash');
    $card = createTestMethod($this, 'card');

    expect($cash->type->affectsCashDrawer())->toBeTrue();
    expect($card->type->affectsCashDrawer())->toBeFalse();
});

test('payment method tipo card requiere referencia', function () {
    expect(PaymentMethodType::CARD->requiresReference())->toBeTrue();
    expect(PaymentMethodType::CASH->requiresReference())->toBeFalse();
});

test('acceptsAmount valida el monto maximo', function () {
    $method = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'LIMITED-' . uniqid(),
        'name_translations' => ['es' => 'Limitado'],
        'type' => 'card',
        'max_amount' => 50000,
    ]);

    expect($method->acceptsAmount(30000))->toBeTrue();
    expect($method->acceptsAmount(60000))->toBeFalse();
});

// ============================================
// CashSession
// ============================================

test('se puede crear una sesion de caja', function () {
    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'session_number' => 'SES-' . uniqid(),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    expect($session->id)->not->toBeNull();
    expect($session->status)->toBe(CashSessionStatus::OPEN);
    expect($session->canReceivePayments())->toBeTrue();
});

test('una sesion cerrada no puede recibir pagos', function () {
    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'session_number' => 'SES-' . uniqid(),
        'status' => CashSessionStatus::CLOSED,
        'opening_amount' => 50000,
        'closing_amount' => 150000,
        'opened_at' => now()->subHours(8),
        'closed_at' => now(),
    ]);

    expect($session->canReceivePayments())->toBeFalse();
});

// ============================================
// Bill
// ============================================

test('se puede crear un bill para split', function () {
    $order = createTestOrder($this);

    $bill = Bill::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'bill_number' => Bill::generateBillNumber($order->order_number, 1),
        'type' => BillType::EQUAL_SPLIT,
        'subtotal' => 5000,
        'tax_amount' => 950,
        'tip_amount' => 0,
        'total' => 5950,
        'paid_amount' => 0,
        'remaining_amount' => 5950,
        'status' => BillStatus::OPEN,
        'guest_count' => 2,
    ]);

    expect($bill->id)->not->toBeNull();
    expect($bill->bill_number)->toBe($order->order_number . '-1');
    expect($bill->isPayable())->toBeTrue();
});

test('registerPaymentAmount actualiza montos y estado del bill', function () {
    $order = createTestOrder($this);

    $bill = Bill::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'bill_number' => 'BILL-' . uniqid(),
        'type' => BillType::CUSTOM_AMOUNT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
        'paid_amount' => 0,
        'remaining_amount' => 11900,
        'status' => BillStatus::OPEN,
    ]);

    // Pago parcial
    $bill->registerPaymentAmount(5000);
    expect($bill->status)->toBe(BillStatus::PARTIAL);
    expect((float) $bill->remaining_amount)->toBe(6900.0);

    // Pago completo
    $bill->registerPaymentAmount(6900);
    expect($bill->status)->toBe(BillStatus::PAID);
    expect($bill->isFullyPaid())->toBeTrue();
});

// ============================================
// Payment
// ============================================

test('se puede crear un pago completo', function () {
    $order = createTestOrder($this);
    $method = createTestMethod($this, 'cash');
    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'session_number' => 'SES-' . uniqid(),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $payment = Payment::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'cash_session_id' => $session->id,
        'payment_method_id' => $method->id,
        'user_id' => $this->cashier->id,
        'payment_number' => Payment::generatePaymentNumber($this->branch->code),
        'method_code' => 'cash',
        'amount' => 11900,
        'tip_amount' => 1190,
        'total_amount' => Payment::calculateTotal(11900, 1190),
        'status' => PaymentStatus::COMPLETED,
        'idempotency_key' => 'IDEM-' . uniqid(),
        'paid_at' => now(),
    ]);

    expect($payment->id)->not->toBeNull();
    expect((float) $payment->total_amount)->toBe(13090.0);
    expect($payment->isSuccessful())->toBeTrue();
});

test('calculateTotal suma amount y tip correctamente', function () {
    expect(Payment::calculateTotal(10000, 1000))->toBe(11000.0);
    expect(Payment::calculateTotal(10000))->toBe(10000.0);
    expect(Payment::calculateTotal(99.99, 0.01))->toBe(100.0);
});

test('generatePaymentNumber genera formato correcto', function () {
    $paymentNumber = Payment::generatePaymentNumber('PAY');

    expect($paymentNumber)->toStartWith('PAY-PAY-');
    expect(strlen($paymentNumber))->toBeGreaterThan(10);
});
