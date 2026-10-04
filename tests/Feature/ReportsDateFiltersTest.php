<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Catalog\Domain\Entities\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'RF-' . uniqid(),
        'legal_name' => 'Reports Filter Test',
        'trade_name' => 'RF Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'RF',
        'name' => 'RF Branch',
    ]);

    $this->manager = User::create([
        'name' => 'Manager RF',
        'email' => 'rf-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);

    $this->token = JWTAuth::fromUser($this->manager);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'sku' => 'PROD-RF-' . uniqid(),
        'name_translations' => ['es' => 'Producto RF'],
        'description_translations' => ['es' => 'Desc'],
        'base_price' => 5000,
        'tax_rate' => 19,
        'is_active' => true,
    ]);

    // Orden hace 3 días (dentro del rango de 5 días)
    $this->order3daysAgo = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'RF-3D-' . uniqid(),
        'status' => 'paid',
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
        'paid_at' => Carbon::now()->subDays(3)->setTime(12, 0),
    ]);

    OrderItem::create([
        'company_id' => $this->company->id,
        'order_id' => $this->order3daysAgo->id,
        'product_id' => $this->product->id,
        'name_snapshot' => 'Producto RF',
        'unit_price_snapshot' => 5000,
        'quantity' => 2,
        'subtotal' => 10000,
    ]);

    // Orden hace 10 días (FUERA del rango de 5 días)
    $this->order10daysAgo = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'RF-10D-' . uniqid(),
        'status' => 'paid',
        'subtotal' => 15000,
        'tax_amount' => 2850,
        'total' => 17850,
        'paid_at' => Carbon::now()->subDays(10)->setTime(14, 0),
    ]);

    OrderItem::create([
        'company_id' => $this->company->id,
        'order_id' => $this->order10daysAgo->id,
        'product_id' => $this->product->id,
        'name_snapshot' => 'Producto RF',
        'unit_price_snapshot' => 5000,
        'quantity' => 3,
        'subtotal' => 15000,
    ]);
});

test('GET /reports/dashboard respeta filtro from_date y to_date', function () {
    // Rango: últimos 5 días (debe incluir solo orden de hace 3 días)
    $from = Carbon::now()->subDays(5)->format('Y-m-d');
    $to = Carbon::now()->format('Y-m-d');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson("/api/v1/reports/dashboard?from_date={$from}&to_date={$to}");

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data['today_orders_count'])->toBe(1);
    expect($data['today_sales'])->toBe(11900);
});

test('GET /reports/top-products respeta filtro de fechas', function () {
    // Rango: últimos 15 días (debe incluir ambas órdenes)
    $from = Carbon::now()->subDays(15)->format('Y-m-d');
    $to = Carbon::now()->format('Y-m-d');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson("/api/v1/reports/top-products?from_date={$from}&to_date={$to}");

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->toHaveCount(1);
    expect($data[0]['quantity'])->toBe(5); // 2 + 3 unidades
    expect($data[0]['revenue'])->toBe(25000); // 10000 + 15000
});

test('GET /reports/sales-by-hour respeta filtro de fechas', function () {
    $from = Carbon::now()->subDays(5)->format('Y-m-d');
    $to = Carbon::now()->format('Y-m-d');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson("/api/v1/reports/sales-by-hour?from_date={$from}&to_date={$to}");

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->toHaveCount(24);
    $totalOrders = array_sum(array_column($data, 'orders_count'));
    expect($totalOrders)->toBe(1); // Solo la orden de hace 3 días
});

test('GET /reports/dashboard valida que from_date <= to_date', function () {
    $from = Carbon::now()->format('Y-m-d');
    $to = Carbon::now()->subDays(5)->format('Y-m-d'); // Antes que from

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson("/api/v1/reports/dashboard?from_date={$from}&to_date={$to}");

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['from_date']);
});

test('GET /reports/dashboard valida rango máximo de 365 días', function () {
    $from = Carbon::now()->subDays(400)->format('Y-m-d');
    $to = Carbon::now()->format('Y-m-d');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson("/api/v1/reports/dashboard?from_date={$from}&to_date={$to}");

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['from_date']);
});

test('GET /reports/dashboard usa rango por defecto cuando no se envían fechas', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson('/api/v1/reports/dashboard');

    $response->assertStatus(200);

    // Debe retornar filtros aplicados
    $response->assertJsonStructure([
        'data' => ['today_sales', 'today_orders_count', 'average_ticket', 'today_tips'],
        'filters' => ['from', 'to'],
    ]);

    // Rango por defecto es 7 días, la orden de hace 10 días está fuera
    $data = $response->json('data');
    expect($data['today_orders_count'])->toBe(1); // Solo la orden de hace 3 días
    expect($data['today_sales'])->toBe(11900);
});
