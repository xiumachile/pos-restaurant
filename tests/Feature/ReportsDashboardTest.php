<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Catalog\Domain\Entities\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'REP-' . uniqid(),
        'legal_name' => 'Reports Test Company',
        'trade_name' => 'Reports Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'REP',
        'name' => 'Reports Branch',
    ]);

    $this->manager = User::create([
        'name' => 'Manager Reports',
        'email' => 'rep-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);

    $this->token = JWTAuth::fromUser($this->manager);

    // Crear producto de prueba con todos los campos requeridos
    $this->product = Product::create([
        'company_id' => $this->company->id,
        'sku' => 'PROD-TEST-' . uniqid(),
        'name_translations' => ['es' => 'Producto Test', 'zh' => '测试产品'],
        'description_translations' => ['es' => 'Descripción', 'zh' => '描述'],
        'base_price' => 5000,
        'tax_rate' => 19,
        'is_active' => true,
    ]);

    // 3 órdenes pagadas hoy
    for ($i = 1; $i <= 3; $i++) {
        $order = Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'order_number' => "T-" . uniqid() . "-{$i}",
            'status' => 'paid',
            'subtotal' => 10000,
            'tax_amount' => 1900,
            'total' => 11900,
            'paid_at' => now()->subHours($i),
        ]);

        OrderItem::create([
            'company_id' => $this->company->id,
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'name_snapshot' => "Producto {$i}",
            'unit_price_snapshot' => 5000,
            'quantity' => 2,
            'subtotal' => 10000,
        ]);
    }
});

test('GET /reports/dashboard retorna KPIs correctos', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson('/api/v1/reports/dashboard');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'today_sales',
                'today_orders_count',
                'average_ticket',
                'today_tips',
            ],
        ]);

    $data = $response->json('data');
    expect($data['today_orders_count'])->toBe(3);
    expect($data['today_sales'])->toBe(35700); // 3 * 11900
    expect($data['average_ticket'])->toBe(11900);
});

test('GET /reports/top-products retorna ranking', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson('/api/v1/reports/top-products?days=7&limit=10');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['name', 'quantity', 'revenue'],
            ],
        ]);

    $data = $response->json('data');
    expect($data)->toHaveCount(3);
    expect($data[0]['quantity'])->toBe(2);
    expect($data[0]['revenue'])->toBe(10000);
});

test('GET /reports/sales-by-hour retorna distribución horaria', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson('/api/v1/reports/sales-by-hour?days=7');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['hour', 'orders_count', 'total'],
            ],
        ]);

    $data = $response->json('data');
    // Debe retornar las 24 horas
    expect($data)->toHaveCount(24);

    // Suma total debe ser 3 órdenes
    $totalOrders = array_sum(array_column($data, 'orders_count'));
    expect($totalOrders)->toBe(3);
});

test('GET /reports/payment-methods retorna distribución de métodos', function () {
    // Crear orden adicional con pago
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'T-PAY-' . uniqid(),
        'status' => 'paid',
        'subtotal' => 5000,
        'tax_amount' => 950,
        'total' => 5950,
        'paid_at' => now(),
    ]);

    // Crear método de pago con todos los campos requeridos
    $paymentMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'CASH',
        'name_translations' => ['es' => 'Efectivo', 'zh' => '现金'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    Payment::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'payment_method_id' => $paymentMethod->id,
        'user_id' => $this->manager->id,
        'payment_number' => 'PAY-' . uniqid(),
        'method_code' => 'CASH',
        'amount' => 5950,
        'tip_amount' => 500,
        'total_amount' => 6450,
        'status' => 'completed',
        'idempotency_key' => 'test-' . uniqid(),
        'paid_at' => now(),
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson('/api/v1/reports/payment-methods?days=30');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['method_code', 'count', 'total_amount'],
            ],
        ]);

    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['method_code'])->toBe('CASH');
    expect($data[0]['count'])->toBe(1);
    expect($data[0]['total_amount'])->toBe(6450);
});
