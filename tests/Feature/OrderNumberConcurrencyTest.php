<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Services\OrderService;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::forceCreate([
        'tax_id' => '76.999.999-9',
        'legal_name' => 'Concurrency Test SpA',
        'trade_name' => 'Concurrency Test',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'CONC',
        'name' => 'Concurrency Branch',
    ]);

    $this->user = User::forceCreate([
        'name' => 'Test User',
        'email' => 'user-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->orderService = app(OrderService::class);
});

test('generateOrderNumber genera números secuenciales únicos', function () {
    $numbers = [];
    for ($i = 0; $i < 10; $i++) {
        $number = $this->orderService->generateOrderNumber($this->branch->id);
        
        // Crear orden con este número
        Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'waiter_id' => $this->user->id,
            'order_number' => $number,
            'type' => OrderType::DINE_IN,
            'status' => OrderStatus::DRAFT,
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 0,
        ]);
        
        $numbers[] = $number;
    }

    // Verificar que todos los números sean únicos
    expect(count(array_unique($numbers)))->toBe(10);
    
    // Verificar secuencia 0001, 0002, ..., 0010
    expect($numbers[0])->toContain('-0001');
    expect($numbers[9])->toContain('-0010');
});

test('generateOrderNumber genera números diferentes consecutivamente', function () {
    // Crear primera orden
    $num1 = $this->orderService->generateOrderNumber($this->branch->id);
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => $num1,
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 0,
    ]);

    // Crear segunda orden
    $num2 = $this->orderService->generateOrderNumber($this->branch->id);
    
    // Los números deben ser diferentes
    expect($num1)->not->toBe($num2);
    expect($num1)->toContain('-0001');
    expect($num2)->toContain('-0002');
});
