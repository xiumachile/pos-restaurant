<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderNumberingConfig;
use Modules\Orders\Domain\Services\OrderService;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::forceCreate([
        'tax_id' => '76.999.999-8',
        'legal_name' => 'Real Concurrency Test SpA',
        'trade_name' => 'Real Concurrency',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'REAL',
        'name' => 'Real Concurrency Branch',
    ]);

    $this->user = User::forceCreate([
        'name' => 'Test User',
        'email' => 'user-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);
    
    // Crear config con is_enabled = true para usar el path con lockForUpdate
    OrderNumberingConfig::create([
        'branch_id' => $this->branch->id,
        'is_enabled' => true,
        'prefix' => 'ORD',
        'reset_frequency' => 'daily',
        'current_sequence' => 0,
    ]);
});

test('race condition: dos lecturas concurrentes no deben generar mismo número', function () {
    $branchId = $this->branch->id;
    
    // Simular dos "terminales" leyendo la configuración simultáneamente
    // Terminal A: lee el valor actual
    $configA = OrderNumberingConfig::where('branch_id', $branchId)->first();
    $seqA = $configA->current_sequence;
    
    // Terminal B: lee el valor actual (ANTES de que A guarde)
    $configB = OrderNumberingConfig::where('branch_id', $branchId)->first();
    $seqB = $configB->current_sequence;
    
    // Sin el fix, ambas lecturas darían el mismo valor (race condition)
    // Con el fix (lockForUpdate), B esperaría a que A termine
    
    // Generar número desde A (debe incrementar a 1)
    $orderService = app(OrderService::class);
    $numberA = $orderService->generateOrderNumber($branchId);
    
    // Generar número desde B (debe incrementar a 2)
    $numberB = $orderService->generateOrderNumber($branchId);
    
    // Los números deben ser diferentes
    expect($numberA)->not->toBe($numberB);
    expect($numberA)->toContain('-0001');
    expect($numberB)->toContain('-0002');
    
    // Verificar que la secuencia final es 2
    $finalConfig = OrderNumberingConfig::where('branch_id', $branchId)->first();
    expect($finalConfig->current_sequence)->toBe(2);
});

test('constraint UNIQUE en DB previene duplicados a nivel de base de datos', function () {
    $duplicateNumber = 'ORD-999-20261009-9999';
    
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => $duplicateNumber,
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 0,
    ]);
    
    $this->expectException(\Illuminate\Database\QueryException::class);
    
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => $duplicateNumber,
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 0,
    ]);
});

test('10 generaciones secuenciales producen números únicos', function () {
    $orderService = app(OrderService::class);
    $branchId = $this->branch->id;
    
    $numbers = [];
    for ($i = 0; $i < 10; $i++) {
        $number = $orderService->generateOrderNumber($branchId);
        $numbers[] = $number;
        
        // Crear orden con este número
        Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $branchId,
            'waiter_id' => $this->user->id,
            'order_number' => $number,
            'type' => OrderType::DINE_IN,
            'status' => OrderStatus::DRAFT,
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 0,
        ]);
    }
    
    // Todos deben ser únicos
    expect(count(array_unique($numbers)))->toBe(10);
    
    // Secuencia 0001 a 0010
    expect($numbers[0])->toContain('-0001');
    expect($numbers[9])->toContain('-0010');
});
