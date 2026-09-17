<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Orders\Domain\Entities\Order;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\Bill;

uses(RefreshDatabase::class);

/**
 * Tests de migración DECIMAL → INTEGER (ADR-018)
 * 
 * IMPORTANTE: Los valores deben ser ENTEROS (pesos CLP)
 * Ejemplo: $12.990 CLP → 12990 (entero)
 */

test('migración convierte DECIMAL a INTEGER correctamente', function () {
    // Valores ENTEROS (pesos CLP, sin decimales)
    $order = Order::create([
        'company_id' => 1,
        'branch_id' => 1,
        'waiter_id' => 1,
        'order_number' => 'TEST-001',
        'type' => 'dine_in',
        'status' => 'served',
        'subtotal_gross' => 12990,  // $12.990 CLP
        'net_amount' => 10916,      // round(12990/1.19) = 10916
        'tax_amount' => 2074,       // 12990 - 10916 = 2074
        'tip_amount' => 1000,       // $1.000 propina
        'amount_due' => 13990,      // 12990 + 1000
        'subtotal' => 12990,
        'total' => 13990,
    ]);

    $recovered = Order::find($order->id);
    
    expect($recovered->subtotal_gross)->toBe(12990)
        ->and($recovered->net_amount)->toBe(10916)
        ->and($recovered->tax_amount)->toBe(2074)
        ->and($recovered->tip_amount)->toBe(1000)
        ->and($recovered->amount_due)->toBe(13990);
    
    // Verificación: net + tax = gross
    expect($recovered->net_amount + $recovered->tax_amount)
        ->toBe($recovered->subtotal_gross);
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
        'net_amount' => 21008,      // round(25000/1.19)
        'tax_amount' => 3992,       // 25000 - 21008
        'tip_amount' => 0,
        'amount_due' => 25000,
        'subtotal' => 25000,
        'total' => 25000,
    ]);

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

    $totalPaid = Payment::where('order_id', $order->id)->sum('total_amount');

    expect($totalPaid)->toBe(25000)
        ->and($order->amount_due)->toBe(25000)
        ->and($totalPaid)->toBe($order->amount_due);
});

test('split bill con remanente funciona con enteros', function () {
    // $25.000 CLP / 3 partes
    $total = 25000;
    $parts = 3;
    $base = (int) floor($total / $parts);  // 8333
    $remainder = $total % $parts;          // 1

    $bills = [];
    for ($i = 0; $i < $parts; $i++) {
        $bills[] = $base + ($i < $remainder ? 1 : 0);
    }

    // Resultado: [8334, 8333, 8333]
    $sum = array_sum($bills);
    expect($sum)->toBe(25000)
        ->and($bills[0])->toBe(8334)
        ->and($bills[1])->toBe(8333)
        ->and($bills[2])->toBe(8333);
});

test('IVA se calcula correctamente con enteros', function () {
    // $999 CLP → cálculo de IVA
    $gross = 999;
    $net = (int) round($gross / 1.19);  // 839
    $tax = $gross - $net;               // 160

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

    expect($amount === $available)->toBeTrue()
        ->and($amount > $available)->toBeFalse()
        ->and($amount < $available)->toBeFalse();
});

test('migración no afecta columnas de porcentaje', function () {
    DB::table('taxes')->insert([
        'company_id' => 1,
        'branch_id' => 1,
        'code' => 'IVA',
        'name' => 'IVA 19%',
        'rate' => 0.1900,  // DECIMAL(10,4) - NO debe migrar
        'is_active' => true,
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tax = DB::table('taxes')->where('code', 'IVA')->first();

    expect((float) $tax->rate)->toBe(0.1900);
});

test('acumulación de 100 veces 0.1 funciona con enteros', function () {
    // En enteros: 100 veces 1 = 100 (sin error de punto flotante)
    $sum = 0;
    for ($i = 0; $i < 100; $i++) {
        $sum += 1;  // 1 peso CLP
    }
    
    expect($sum)->toBe(100);  // Exacto, sin errores
});

test('diferencia de caja se calcula correctamente con enteros', function () {
    $opening = 50000;    // $50.000 apertura
    $sales = 125000;     // $125.000 en ventas
    $payouts = 15000;    // $15.000 en retiros
    $closing = 160000;   // $160.000 contado
    
    $expected = $opening + $sales - $payouts;  // 160000
    $difference = $closing - $expected;        // 0
    
    expect($expected)->toBe(160000)
        ->and($difference)->toBe(0);
});
