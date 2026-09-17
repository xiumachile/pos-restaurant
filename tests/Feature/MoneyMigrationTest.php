<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Orders\Domain\Entities\Order;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\Bill;

uses(RefreshDatabase::class);

test('migración convierte DECIMAL a INTEGER correctamente', function () {
    // Crear datos de prueba con valores DECIMAL
    $order = Order::create([
        'company_id' => 1,
        'branch_id' => 1,
        'waiter_id' => 1,
        'order_number' => 'TEST-001',
        'type' => 'dine_in',
        'status' => 'served',
        'subtotal_gross' => 12990.00,  // DECIMAL(14,2)
        'net_amount' => 10915.97,
        'tax_amount' => 2074.03,
        'tip_amount' => 1000.00,
        'amount_due' => 13990.00,
        'subtotal' => 12990.00,
        'total' => 13990.00,
    ]);

    // Verificar que los valores se guardaron correctamente
    $recovered = Order::find($order->id);
    
    // Después de la migración, estos deben ser enteros
    expect($recovered->subtotal_gross)->toBe(12990)
        ->and($recovered->tip_amount)->toBe(1000)
        ->and($recovered->amount_due)->toBe(13990);
});

test('operaciones aritméticas funcionan con enteros', function () {
    $order = Order::create([
        'company_id' => 1,
        'branch_id' => 1,
        'waiter_id' => 1,
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

    // Crear payment
    $payment = Payment::create([
        'company_id' => 1,
        'branch_id' => 1,
        'order_id' => $order->id,
        'payment_method_id' => 1,
        'user_id' => 1,
        'amount' => 25000,
        'tip_amount' => 0,
        'total_amount' => 25000,
        'status' => 'completed',
        'idempotency_key' => 'test-key-001',
    ]);

    // Verificar que la suma funciona correctamente
    $totalPaid = Payment::where('order_id', $order->id)
        ->sum('total_amount');

    expect($totalPaid)->toBe(25000)
        ->and($order->amount_due)->toBe(25000)
        ->and($totalPaid)->toBe($order->amount_due);
});

test('split bill con remanente funciona con enteros', function () {
    $order = Order::create([
        'company_id' => 1,
        'branch_id' => 1,
        'waiter_id' => 1,
        'order_number' => 'TEST-003',
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

    // Dividir en 3 partes
    $total = 25000;
    $parts = 3;
    $base = (int) floor($total / $parts);  // 8333
    $remainder = $total % $parts;  // 1

    $bills = [];
    for ($i = 0; $i < $parts; $i++) {
        $bills[] = $base + ($i < $remainder ? 1 : 0);
    }

    // Verificar que la suma es exacta
    $sum = array_sum($bills);
    expect($sum)->toBe(25000)
        ->and($bills[0])->toBe(8334)
        ->and($bills[1])->toBe(8333)
        ->and($bills[2])->toBe(8333);
});

test('IVA se calcula correctamente con enteros', function () {
    $gross = 999;  // $999 CLP
    $net = (int) round($gross / 1.19);  // 839
    $tax = $gross - $net;  // 160

    expect($net)->toBe(839)
        ->and($tax)->toBe(160)
        ->and($net + $tax)->toBe($gross);
});

test('propina con porcentaje funciona con enteros', function () {
    $amount = 25347;
    $tip = (int) round($amount * 0.10);  // 2535

    expect($tip)->toBe(2535);
});

test('comparaciones sin epsilon funcionan correctamente', function () {
    $amount = 10000;
    $available = 10000;

    // Sin epsilon, comparación directa
    expect($amount === $available)->toBeTrue()
        ->and($amount > $available)->toBeFalse()
        ->and($amount < $available)->toBeFalse();
});

test('migración no afecta columnas de porcentaje', function () {
    // Crear impuesto con tasa decimal
    DB::table('taxes')->insert([
        'company_id' => 1,
        'branch_id' => 1,
        'code' => 'IVA',
        'name' => 'IVA 19%',
        'rate' => 0.1900,  // DECIMAL(10,4) - NO debe migrar
        'is_active' => true,
    ]);

    $tax = DB::table('taxes')->where('code', 'IVA')->first();

    // La tasa debe seguir siendo decimal
    expect($tax->rate)->toBe(0.1900);
});
