<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Orders\Domain\Entities\Order;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Accounting\Domain\Entities\Account;

uses(RefreshDatabase::class);

/**
 * Tests de integridad INTEGER en bills (ADR-018)
 * 
 * Verifica que:
 * - Campos monetarios son INTEGER (no DECIMAL)
 * - Operaciones aritméticas funcionan sin pérdida de precisión
 * - Invariantes se mantienen (subtotal + tax - discount + tip = total)
 * 
 * NOTA: La conversión DECIMAL→INTEGER se hace en MoneyMigrationTest.
 * Este test verifica las propiedades invariantes en el model Bill.
 */
beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'BILL-INT-' . uniqid(),
        'legal_name' => 'Bill Integer Test',
        'trade_name' => 'Bill Integer Test',
    ]);

    enableAllCapabilities($this->company);
    Account::seedDefaultsFor($this->company->id);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'BINT',
        'name' => 'Bill Integer Branch',
    ]);
});

test('bills aceptan valores INTEGER en todos los campos monetarios', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'BINT-001',
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $bill = Bill::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'bill_number' => 'TEST-001',
        'type' => 'equal_split',
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 500,
        'tip_amount' => 1000,
        'total' => 11400,
        'paid_amount' => 0,
        'remaining_amount' => 11400,
        'status' => 'open',
    ]);

    $bill->refresh();

    // Verificar tipos (PHP int, no float)
    expect($bill->subtotal)->toBeInt();
    expect($bill->tax_amount)->toBeInt();
    expect($bill->discount_amount)->toBeInt();
    expect($bill->tip_amount)->toBeInt();
    expect($bill->total)->toBeInt();
    expect($bill->paid_amount)->toBeInt();
    expect($bill->remaining_amount)->toBeInt();

    // Verificar valores exactos (sin decimales)
    expect($bill->subtotal)->toBe(10000);
    expect($bill->tax_amount)->toBe(1900);
    expect($bill->discount_amount)->toBe(500);
    expect($bill->tip_amount)->toBe(1000);
    expect($bill->total)->toBe(11400);
});

test('bills soportan pagos parciales sin pérdida de precisión', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'BINT-002',
        'subtotal_gross' => 24800,
        'net_amount' => 20840,
        'tax_amount' => 3960,
        'amount_due' => 24800,
        'subtotal' => 20000,
        'total' => 24800,
    ]);

    $bill = Bill::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'bill_number' => 'TEST-002',
        'type' => 'equal_split',
        'subtotal' => 20000,
        'tax_amount' => 3800,
        'discount_amount' => 1000,
        'tip_amount' => 2000,
        'total' => 24800,
        'paid_amount' => 10000,
        'remaining_amount' => 14800,
        'status' => 'partial',
    ]);

    // Simular pago parcial
    $paymentAmount = 5000;
    $bill->paid_amount += $paymentAmount;
    $bill->remaining_amount -= $paymentAmount;
    $bill->save();

    $bill->refresh();

    expect($bill->paid_amount)->toBe(15000);
    expect($bill->remaining_amount)->toBe(9800);
    
    // Invariante: paid + remaining = total (exacto, sin epsilon)
    expect($bill->paid_amount + $bill->remaining_amount)->toBe($bill->total);
});

test('bills invariante: subtotal + tax - discount + tip = total', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'BINT-003',
        'subtotal_gross' => 18600,
        'net_amount' => 15630,
        'tax_amount' => 2970,
        'amount_due' => 18600,
        'subtotal' => 15000,
        'total' => 18600,
    ]);

    $bill = Bill::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'bill_number' => 'TEST-003',
        'type' => 'equal_split',
        'subtotal' => 15000,
        'tax_amount' => 2850,
        'discount_amount' => 750,
        'tip_amount' => 1500,
        'total' => 18600,
        'paid_amount' => 0,
        'remaining_amount' => 18600,
        'status' => 'open',
    ]);

    // Verificar invariante con enteros exactos
    $calculatedTotal = $bill->subtotal + $bill->tax_amount - $bill->discount_amount + $bill->tip_amount;
    expect($calculatedTotal)->toBe($bill->total);
    expect($calculatedTotal)->toBe(18600);
});

test('bills soportan valores grandes sin overflow', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'BINT-004',
        'subtotal_gross' => 1239998,
        'net_amount' => 1042015,
        'tax_amount' => 197983,
        'amount_due' => 1239998,
        'subtotal' => 999999,
        'total' => 1239998,
    ]);

    $bill = Bill::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'bill_number' => 'TEST-004',
        'type' => 'equal_split',
        'subtotal' => 999999,
        'tax_amount' => 189999,
        'discount_amount' => 50000,
        'tip_amount' => 100000,
        'total' => 1239998,
        'paid_amount' => 500000,
        'remaining_amount' => 739998,
        'status' => 'partial',
    ]);

    $bill->refresh();

    // Verificar que valores grandes se preservan
    expect($bill->subtotal)->toBe(999999);
    expect($bill->total)->toBe(1239998);
    expect($bill->paid_amount)->toBe(500000);
    expect($bill->remaining_amount)->toBe(739998);
    
    // Invariante
    expect($bill->paid_amount + $bill->remaining_amount)->toBe($bill->total);
});

test('bill casts exponen todos los campos como integer', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'BINT-005',
        'subtotal_gross' => 5000,
        'net_amount' => 4202,
        'tax_amount' => 798,
        'amount_due' => 5000,
        'subtotal' => 5000,
        'total' => 5000,
    ]);

    $bill = Bill::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'bill_number' => 'TEST-005',
        'type' => 'equal_split',
        'subtotal' => 5000,
        'tax_amount' => 950,
        'discount_amount' => 250,
        'tip_amount' => 500,
        'total' => 6200,
        'paid_amount' => 0,
        'remaining_amount' => 6200,
        'status' => 'open',
    ]);

    // Verificar que los casts están correctamente configurados
    $casts = $bill->getCasts();
    
    expect($casts['subtotal'])->toBe('integer');
    expect($casts['tax_amount'])->toBe('integer');
    expect($casts['discount_amount'])->toBe('integer');
    expect($casts['tip_amount'])->toBe('integer');
    expect($casts['total'])->toBe('integer');
    expect($casts['paid_amount'])->toBe('integer');
    expect($casts['remaining_amount'])->toBe('integer');
});
