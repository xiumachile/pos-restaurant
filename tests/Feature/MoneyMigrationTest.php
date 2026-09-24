<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'MIG-' . uniqid(),
        'legal_name' => 'Money Migration Test',
        'trade_name' => 'Migration Test',
    ]);
    
    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'MIG-' . uniqid(),
        'name' => 'Migration Branch',
    ]);
    
    $this->user = User::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Migration Tester',
        'email' => 'mig-' . uniqid() . '@test.com',
        'password' => bcrypt('password123'),
        'role' => 'cashier',
    ]);
});

/**
 * P2-001: Validar que la migración realmente convirtió DECIMAL a INTEGER.
 * No basta con que el modelo acepte enteros; la columna en PostgreSQL debe ser 'integer' o 'bigint'.
 */
test('la migración convierte columnas DECIMAL a INTEGER en information_schema', function () {
    // Tablas y columnas que deberían haber sido convertidas de decimal/numeric a integer
    $expectedIntegerColumns = [
        'orders' => ['subtotal_gross', 'net_amount', 'tax_amount', 'tip_amount', 'amount_due', 'subtotal', 'total'],
        'order_items' => ['unit_price_snapshot', 'subtotal', 'tax_amount'],
        'bills' => ['subtotal', 'tax_amount', 'discount_amount', 'tip_amount', 'total', 'paid_amount', 'remaining_amount'],
        'payments' => ['amount', 'tip_amount', 'total_amount'],
        'products' => ['base_price'],
    ];

    foreach ($expectedIntegerColumns as $tableName => $columns) {
        foreach ($columns as $columnName) {
            // Consultar information_schema para verificar el tipo de dato real en la BD
            $columnInfo = DB::select("
                SELECT data_type, numeric_precision, numeric_scale 
                FROM information_schema.columns 
                WHERE table_name = ? AND column_name = ?
            ", [$tableName, $columnName]);

            expect($columnInfo)->not->toBeEmpty("La columna {$tableName}.{$columnName} debería existir");
            
            $dataType = strtolower($columnInfo[0]->data_type);
            $numericScale = $columnInfo[0]->numeric_scale;
            
            // 1. Debe ser 'integer' o 'bigint', NO 'numeric' ni 'decimal'
            expect($dataType)->toBeIn(
                ['integer', 'bigint'], 
                "La columna {$tableName}.{$columnName} debe ser integer/bigint, pero es: {$dataType}"
            );
            
            // 2. numeric_scale debe ser 0 o null (garantiza que no hay decimales)
            // Nota: PostgreSQL a veces reporta numeric_precision=32 para integer, lo cual es normal.
            expect($numericScale)->toBeIn([0, null], 
                "La columna {$tableName}.{$columnName} debe tener scale 0 o null, pero tiene: {$numericScale}"
            );
        }
    }
});

test('operaciones aritméticas y de agregación funcionan correctamente con enteros', function () {
    $order = \Modules\Orders\Domain\Entities\Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-' . uniqid(),
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

    $paymentMethod = \Modules\Payments\Domain\Entities\PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'cash-' . uniqid(),
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    \Modules\Payments\Domain\Entities\Payment::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'payment_method_id' => $paymentMethod->id,
        'user_id' => $this->user->id,
        'payment_number' => 'PAY-' . uniqid(),
        'method_code' => 'cash',
        'amount' => 25000,
        'tip_amount' => 0,
        'total_amount' => 25000,
        'status' => 'completed',
        'idempotency_key' => \Illuminate\Support\Str::uuid()->toString(),
    ]);

    // Verificar que SUM() devuelve un entero y no un string decimal
    $totalPaid = \Modules\Payments\Domain\Entities\Payment::where('order_id', $order->id)->sum('total_amount');

    expect($totalPaid)->toBeInt('La suma de total_amount debe ser un entero')
        ->and($totalPaid)->toBe(25000);
});

test('cálculo de IVA con enteros mantiene la precisión sin floats', function () {
    $gross = 9990;
    $net = (int) round($gross / 1.19);
    $tax = $gross - $net;

    expect($net)->toBeInt()->toBe(8395)
        ->and($tax)->toBeInt()->toBe(1595)
        ->and($net + $tax)->toBe($gross);
});
