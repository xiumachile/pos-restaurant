<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Catalog\Domain\Entities\MenuItem;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'OI-API-' . uniqid(),
        'legal_name' => 'Order Item API Company',
        'trade_name' => 'Order Item API Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'OI-API',
        'name' => 'Order Item API Branch',
    ]);

    $this->user = User::create([
        'name' => 'Order Item User',
        'email' => 'orderitem@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'admin',
    ]);

    // Crear categoría
    $this->category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Platos Principales', 'zh' => '主菜'],
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Crear producto (el que realmente tiene el nombre y precio)
    $this->product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->category->id,
        'name_translations' => ['es' => 'Hamburguesa Clásica', 'zh' => '经典汉堡'],
        'description_translations' => ['es' => 'Con queso y tocino'],
        'base_price' => 5990,
        'is_active' => true,
    ]);

    // Crear MenuItem que apunta al producto (CRÍTICO: product_id)
    $this->menuItem = MenuItem::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $this->product->id,
        'base_price' => 5990,
        'is_active' => true,
    ]);

    $this->token = JWTAuth::fromUser($this->user);
});

function headers(): array
{
    return [
        'Authorization' => "Bearer " . test()->token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

test('POST /api/v1/orders/{uuid}/items agrega item al pedido', function () {
    // Verificar que el MenuItem se creó con product_id
    expect($this->menuItem->product_id)->toBe($this->product->id);
    expect($this->menuItem->product)->not->toBeNull();
    expect($this->menuItem->product->name_translations['es'])->toBe('Hamburguesa Clásica');

    // Crear pedido draft
    $createResponse = $this->withHeaders(headers())
        ->postJson('/api/v1/orders', ['type' => 'takeout']);
    
    $orderUuid = $createResponse->json('data.uuid');

    // Agregar item
    $response = $this->withHeaders(headers())
        ->postJson("/api/v1/orders/{$orderUuid}/items", [
            'menu_item_uuid' => $this->menuItem->uuid,
            'quantity' => 2,
        ]);

    $response->assertStatus(201);

    $data = $response->json('data');
    expect($data['items'][0]['name'])->toBe('Hamburguesa Clásica');
    expect($data['items'][0]['quantity'])->toBe(2);
    expect((float) $data['items'][0]['unit_price'])->toBe(5990.0);
    expect((float) $data['items'][0]['subtotal'])->toBe(11980.0);
    expect((float) $data['subtotal'])->toBe(11980.0);
});

test('agregar múltiples items recalcula totales correctamente', function () {
    $createResponse = $this->withHeaders(headers())
        ->postJson('/api/v1/orders', ['type' => 'takeout']);
    
    $orderUuid = $createResponse->json('data.uuid');

    // Agregar 2 items
    $this->withHeaders(headers())
        ->postJson("/api/v1/orders/{$orderUuid}/items", [
            'menu_item_uuid' => $this->menuItem->uuid,
            'quantity' => 1,
        ]);

    $this->withHeaders(headers())
        ->postJson("/api/v1/orders/{$orderUuid}/items", [
            'menu_item_uuid' => $this->menuItem->uuid,
            'quantity' => 2,
        ]);

    // Verificar totales (3 hamburguesas = 17970 + IVA 19% = 21384.3)
    $response = $this->withHeaders(headers())
        ->getJson("/api/v1/orders/{$orderUuid}");

    $response->assertOk()
        ->assertJsonCount(2, 'data.items');

    expect((float) $response->json('data.subtotal'))->toBe(17970.0);
});

test('DELETE /api/v1/orders/{uuid}/items/{itemUuid} quita item', function () {
    $createResponse = $this->withHeaders(headers())
        ->postJson('/api/v1/orders', ['type' => 'takeout']);
    
    $orderUuid = $createResponse->json('data.uuid');

    // Agregar item
    $addResponse = $this->withHeaders(headers())
        ->postJson("/api/v1/orders/{$orderUuid}/items", [
            'menu_item_uuid' => $this->menuItem->uuid,
            'quantity' => 1,
        ]);

    $itemUuid = $addResponse->json('data.items.0.uuid');

    // Quitar item
    $response = $this->withHeaders(headers())
        ->deleteJson("/api/v1/orders/{$orderUuid}/items/{$itemUuid}");

    $response->assertOk()
        ->assertJsonCount(0, 'data.items');

    expect((float) $response->json('data.subtotal'))->toBe(0.0);
});

test('deniega agregar items a pedido confirmado', function () {
    $createResponse = $this->withHeaders(headers())
        ->postJson('/api/v1/orders', ['type' => 'takeout']);
    
    $orderUuid = $createResponse->json('data.uuid');

    // Confirmar pedido
    $this->withHeaders(headers())
        ->postJson("/api/v1/orders/{$orderUuid}/confirm");

    // Intentar agregar item
    $response = $this->withHeaders(headers())
        ->postJson("/api/v1/orders/{$orderUuid}/items", [
            'menu_item_uuid' => $this->menuItem->uuid,
            'quantity' => 1,
        ]);

    $response->assertStatus(422);
});

test('deniega agregar item con quantity invalida', function () {
    $createResponse = $this->withHeaders(headers())
        ->postJson('/api/v1/orders', ['type' => 'takeout']);
    
    $orderUuid = $createResponse->json('data.uuid');

    $response = $this->withHeaders(headers())
        ->postJson("/api/v1/orders/{$orderUuid}/items", [
            'menu_item_uuid' => $this->menuItem->uuid,
            'quantity' => 0,
        ]);

    $response->assertStatus(422);
});

test('deniega agregar item sin menu_item_uuid', function () {
    $createResponse = $this->withHeaders(headers())
        ->postJson('/api/v1/orders', ['type' => 'takeout']);
    
    $orderUuid = $createResponse->json('data.uuid');

    $response = $this->withHeaders(headers())
        ->postJson("/api/v1/orders/{$orderUuid}/items", [
            'quantity' => 1,
        ]);

    $response->assertStatus(422);
});
