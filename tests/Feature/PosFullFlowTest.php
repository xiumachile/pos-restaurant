<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $this->company = Company::create([
        'tax_id' => 'POSFLOW-' . uniqid(),
        'legal_name' => 'POS Flow Test',
        'trade_name' => 'POS Flow',
    ]);

    enableAllCapabilities($this->company);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'POSF',
        'name' => 'POS Flow Branch',
    ]);

    $this->cashier = User::create([
        'name' => 'Test Cashier',
        'email' => 'cashier-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
        'pos_pin_hash' => password_hash('1234', PASSWORD_BCRYPT),
    ]);

    $this->cashMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'cash',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    // Abrir sesión de caja
    $this->cashSession = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'session_number' => 'CS-FLOW-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 100000,
        'opened_at' => now(),
    ]);

    // Crear mesa (requerida para dine_in)
    $this->table = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'table_number' => 'T-' . strtoupper(substr(uniqid(), -4)),
        'capacity' => 4,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón Principal'],
    ]);

    // Crear categoría para el producto
    $this->category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Categoría Test'],
        'sort_order' => 1,
    ]);

    // Crear producto para agregar al pedido
    // base_price = 10000, tax_rate = 19% → total = 11900
    $this->product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->category->id,
        'name_translations' => ['es' => 'Producto Test'],
        'base_price' => 10000,
        'tax_rate' => 19.00,
        'is_active' => true,
    ]);
});

test('flujo POS completo: pos-session → login → orden → pago', function () {
    // PASO 1: Crear sesión POS con PIN
    $sessionResponse = $this->postJson('/api/v1/auth/pos-session', [
        'branch_id' => $this->branch->id,
        'pin' => '1234',
    ]);

    $sessionResponse->assertStatus(201);
    $sessionToken = $sessionResponse->json('session_token');
    expect($sessionToken)->toBeString()->toHaveLength(32);

    // PASO 2: Login con session_token (O(1))
    $loginResponse = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => $this->branch->id,
        'session_token' => $sessionToken,
    ]);

    $loginResponse->assertStatus(200);
    $jwtToken = $loginResponse->json('access_token');
    expect($jwtToken)->toBeString();

    // PASO 3: Crear orden en DRAFT (sin totales, se calculan desde items)
    $idempotencyKey = Str::uuid()->toString();
    $orderResponse = $this->withHeaders([
        'Authorization' => 'Bearer ' . $jwtToken,
        'Idempotency-Key' => $idempotencyKey,
    ])->postJson('/api/v1/orders', [
        'order_number' => 'ORD-FLOW-' . uniqid(),
        'type' => 'dine_in',
        'table_uuid' => $this->table->uuid,
    ]);

    $orderResponse->assertStatus(201);
    $orderUuid = $orderResponse->json('data.uuid');

    // PASO 3.5: Agregar item al pedido (esto calcula subtotal/tax/total)
    $itemIdempotencyKey = Str::uuid()->toString();
    $itemResponse = $this->withHeaders([
        'Authorization' => 'Bearer ' . $jwtToken,
        'Idempotency-Key' => $itemIdempotencyKey,
    ])->postJson("/api/v1/orders/{$orderUuid}/items", [
        'product_uuid' => $this->product->uuid,
        'quantity' => 1,
    ]);
    
    $itemResponse->assertStatus(201);

    // PASO 3.6: Verificar totales calculados por la orden
    $order = \Modules\Orders\Domain\Entities\Order::where('uuid', $orderUuid)->firstOrFail();
    
    expect((float) $order->subtotal)->toBe(10000.0);
    expect((float) $order->tax_amount)->toBe(1900.0);
    expect((float) $order->total)->toBe(11900.0);

    // Actualizar estado directamente a 'served' para permitir pagos
    // (las transiciones de estado ya tienen sus propios tests)
    $order->status = OrderStatus::SERVED;
    $order->save();
    
    // PASO 4: Crear pago por el total real de la orden
    $paymentIdempotencyKey = Str::uuid()->toString();
    $paymentResponse = $this->withHeaders([
        'Authorization' => 'Bearer ' . $jwtToken,
        'Idempotency-Key' => $paymentIdempotencyKey,
    ])->postJson('/api/v1/billing/payments', [
        'order_uuid' => $orderUuid,
        'payment_method_uuid' => $this->cashMethod->uuid,
        'amount' => $order->total,
    ]);

    $paymentResponse->assertStatus(201);
    expect($paymentResponse->json('data.status'))->toBe('completed');
});

test('idempotencia: mismo Idempotency-Key retorna misma respuesta', function () {
    // Login
    $sessionResponse = $this->postJson('/api/v1/auth/pos-session', [
        'branch_id' => $this->branch->id,
        'pin' => '1234',
    ]);
    $sessionToken = $sessionResponse->json('session_token');

    $loginResponse = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => $this->branch->id,
        'session_token' => $sessionToken,
    ]);
    $jwtToken = $loginResponse->json('access_token');

    // Crear orden con Idempotency-Key
    $idempotencyKey = Str::uuid()->toString();
    $orderData = [
        'order_number' => 'ORD-IDEMP-' . uniqid(),
        'type' => 'dine_in',
        'table_uuid' => $this->table->uuid,
    ];
    
    // Nota: el test de idempotencia crea el mismo pedido 2 veces
    // con el mismo Idempotency-Key, por lo que solo se crea 1 pedido.
    // Los items se agregan después de confirmar que existe.

    // Primer request
    $response1 = $this->withHeaders([
        'Authorization' => 'Bearer ' . $jwtToken,
        'Idempotency-Key' => $idempotencyKey,
    ])->postJson('/api/v1/orders', $orderData);

    $response1->assertStatus(201);
    $orderUuid1 = $response1->json('data.uuid');

    // Actualizar estado directamente a 'served' para permitir pagos
    $order1 = \Modules\Orders\Domain\Entities\Order::where('uuid', $orderUuid1)->first();
    $order1->status = OrderStatus::SERVED;
    $order1->save();

    // Segundo request con MISMO Idempotency-Key
    $response2 = $this->withHeaders([
        'Authorization' => 'Bearer ' . $jwtToken,
        'Idempotency-Key' => $idempotencyKey,
    ])->postJson('/api/v1/orders', $orderData);

    $response2->assertStatus(201);
    $orderUuid2 = $response2->json('data.uuid');

    // Debe retornar la misma orden (idempotencia)
    expect($orderUuid1)->toBe($orderUuid2);
});

test('session_token es one-time use', function () {
    // Crear sesión
    $sessionResponse = $this->postJson('/api/v1/auth/pos-session', [
        'branch_id' => $this->branch->id,
        'pin' => '1234',
    ]);
    $sessionToken = $sessionResponse->json('session_token');

    // Primer login (éxito)
    $loginResponse1 = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => $this->branch->id,
        'session_token' => $sessionToken,
    ]);
    $loginResponse1->assertStatus(200);

    // Segundo login con MISMO token (debe fallar)
    $loginResponse2 = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => $this->branch->id,
        'session_token' => $sessionToken,
    ]);
    $loginResponse2->assertStatus(401);
});
