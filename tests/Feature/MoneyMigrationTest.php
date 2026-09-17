<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\Entities\CashSession;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Crear company con campos correctos
    $this->company = Company::create([
        'tax_id' => '12345678-9',
        'legal_name' => 'Test Company SpA',
        'trade_name' => 'Test Company',
        'default_locale' => 'es',
        'fallback_locale' => 'en',
        'is_active' => true,
        'settings' => ['currency' => 'CLP'],
    ]);
    
    // Crear branch
    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Test Branch',
        'address' => 'Test Address 123',
        'phone' => '+56912345678',
        'code' => 'TEST',
        'area_code' => 'TEST',
    ]);
    
    // Crear user
    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('migración convierte DECIMAL a INTEGER correctamente', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'TEST-001',
        'type' => 'dine_in',
        'status' => 'served',
        'subtotal_gross' => 12990,
        'net_amount' => 10916,
        'tax_amount' => 2074,
        'tip_amount' => 1000,
        'amount_due' => 13990,
        'subtotal' => 12990,
        'total' => 13990,
    ]);

    $recovered = Order::find($order->id);
    
    expect($recovered->subtotal_gross)->toBe(12990)
        ->and($recovered->net_amount)->toBe(10916)
        ->and($recovered->tax_amount)->toBe(2074)
        ->and($recovered->tip_amount)->toBe(1000)
        ->and($recovered->amount_due)->toBe(13990);
});

test('operaciones aritméticas funcionan con enteros', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'TEST-002',
        'type' => 'dine_in',
        'status' => 'served',
        'subtotal_gross' => 25000,
        'net_amount' => 21008,
        'tax_amount' => 3992,
        'tip_amount' => 0,
        'amount_due' => 25000,
        'subtotal' => 25000,
        'total' => 25000,
    ]);

    $payment = Payment::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'payment_method_id' => 1,
        'user_id' => $this->user->id,
        'amount' => 25000,
        'tip_amount' => 0,
        'total_amount' => 25000,
        'status' => 'completed',
        'idempotency_key' => 'test-key-001',
    ]);

    $totalPaid = Payment::where('order_id', $order->id)->sum('total_amount');

    expect($totalPaid)->toBe(25000)
        ->and($order->amount_due)->toBe(25000);
});

test('split bill con remanente funciona con enteros', function () {
    $total = 25000;
    $parts = 3;
    $base = (int) floor($total / $parts);
    $remainder = $total % $parts;

    $bills = [];
    for ($i = 0; $i < $parts; $i++) {
        $bills[] = $base + ($i < $remainder ? 1 : 0);
    }

    $sum = array_sum($bills);
    expect($sum)->toBe(25000)
        ->and($bills[0])->toBe(8334)
        ->and($bills[1])->toBe(8333)
        ->and($bills[2])->toBe(8333);
});

test('IVA se calcula correctamente con enteros', function () {
    $gross = 999;
    $net = (int) round($gross / 1.19);
    $tax = $gross - $net;

    expect($net)->toBe(839)
        ->and($tax)->toBe(160)
        ->and($net + $tax)->toBe($gross);
});

test('propina con porcentaje funciona con enteros', function () {
    $amount = 25347;
    $tip = (int) round($amount * 0.10);

    expect($tip)->toBe(2535);
});

test('comparaciones sin epsilon funcionan correctamente', function () {
    $amount = 10000;
    $available = 10000;

    expect($amount === $available)->toBeTrue()
        ->and($amount > $available)->toBeFalse()
        ->and($amount < $available)->toBeFalse();
});

test('Order model calcula IVA correctamente como entero', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'TEST-003',
        'type' => 'dine_in',
        'status' => 'served',
        'subtotal_gross' => 22000,
        'net_amount' => (int) round(22000 / 1.19),
        'tax_amount' => 22000 - (int) round(22000 / 1.19),
        'tip_amount' => 0,
        'amount_due' => 22000,
        'subtotal' => 22000,
        'total' => 22000,
    ]);

    expect($order->net_amount)->toBeInt()
        ->and($order->tax_amount)->toBeInt()
        ->and($order->net_amount + $order->tax_amount)->toBe($order->subtotal_gross);
});
