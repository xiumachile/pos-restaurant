<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create(['tax_id' => 'TEST-UX03', 'legal_name' => 'Test Co', 'trade_name' => 'Test']);
    $this->branch = Branch::create(['company_id' => $this->company->id, 'code' => 'T1', 'name' => 'Test Branch']);

    $this->user = User::create([
        'name' => 'Mesero',
        'email' => 'mesero@test.test',
        'password' => bcrypt('pw'),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->token = JWTAuth::fromUser($this->user);
    
    $this->product = Product::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'BURG-001',
        'name_translations' => ['es' => 'Hamburguesa Clásica'],
        'base_price' => 8500,
        'is_active' => true,
    ]);
});

test('UX-03: Un pedido en estado DRAFT puede ser actualizado con nuevos items sin perder datos', function () {
    // 1. Crear pedido inicial en estado DRAFT
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-UX03-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 8500,
        'tax_amount' => 1615,
        'total' => 10115,
    ]);

    // Agregar primer item con TODOS los campos requeridos (incluyendo name_snapshot)
    OrderItem::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'product_id' => $this->product->id,
        'name_snapshot' => 'Hamburguesa Clásica',
        'quantity' => 1,
        'unit_price_snapshot' => 8500,
        'subtotal' => 8500,
        'tax_amount' => 1615,
        'tax_rate_snapshot' => 19.0,
        'tax_name_snapshot' => 'IVA 19%',
    ]);

    // 2. Simular que el mesero actualiza el DRAFT (cambia cantidad a 2)
    $updatePayload = [
        'status' => 'draft',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => 8500,
            ]
        ]
    ];

    $response = $this->withHeaders(apiHeaders($this->token))
        ->putJson("/api/v1/orders/{$order->uuid}", $updatePayload);

    // 3. Verificar que la actualización fue exitosa (200 OK)
    $response->assertStatus(200);
    
    // 4. Verificar que el pedido sigue en DRAFT
    $order->refresh();
    expect($order->status->value)->toBe('draft');
    
    // 5. Verificar que los items se actualizaron correctamente
    $itemsCount = OrderItem::where('order_id', $order->id)->count();
    expect($itemsCount)->toBe(1, "Debe haber 1 línea de item (con cantidad actualizada), no duplicar líneas");
    
    $updatedItem = OrderItem::where('order_id', $order->id)->first();
    expect($updatedItem->quantity)->toBe(2);
    expect($updatedItem->subtotal)->toBe(17000);
});

test('UX-03: Un pedido CONFIRMED no puede ser modificado (protección de integridad)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-UX03-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED, // Ya confirmado
        'subtotal' => 8500,
        'tax_amount' => 1615,
        'total' => 10115,
    ]);

    $updatePayload = [
        'status' => 'confirmed',
        'notes' => 'Nota agregada tarde',
    ];

    $response = $this->withHeaders(apiHeaders($this->token))
        ->putJson("/api/v1/orders/{$order->uuid}", $updatePayload);

    // Debe fallar porque no es editable (403 Forbidden o 422 Unprocessable)
    expect(in_array($response->status(), [403, 422]))->toBeTrue("Debe rechazar la modificación de un pedido confirmado");
});
