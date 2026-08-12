<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Inventory\Domain\Entities\InventoryItem;
use Modules\Inventory\Domain\Entities\InventoryStock;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Catalog\Domain\Entities\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'INV-ORD-' . uniqid(),
        'legal_name' => 'Inventory Order Integration',
        'trade_name' => 'Integration Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'INT',
        'name' => 'Integration Branch',
    ]);

    $this->waiter = User::create([
        'name' => 'Test Waiter',
        'email' => 'waiter-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    // Crear categoria y producto del catalogo
    $this->category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Test Category', 'zh' => '测试分类'],
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->category->id,
        'name_translations' => ['es' => 'Papa 1kg', 'zh' => '土豆1公斤'],
        'description_translations' => ['es' => 'Papa fresca'],
        'base_price' => 500,
        'is_active' => true,
    ]);

    // Crear menu_item con product_id valido (referencia products, NO inventory_items)
    $this->menuItem = MenuItem::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'base_price' => 500,
        'is_active' => true,
    ]);

    // Crear item de inventario (separado del catalogo)
    // El listener lo busca por SKU = name_snapshot del order item
    $this->inventoryItem = InventoryItem::create([
        'company_id' => $this->company->id,
        'sku' => 'PAPA-001',
        'name_translations' => ['es' => 'Papa 1kg', 'zh' => '土豆1公斤'],
        'unit' => 'kg',
        'cost_price' => 1500,
        'min_stock' => 5,
    ]);

    // Dar stock inicial
    InventoryStock::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'inventory_item_id' => $this->inventoryItem->id,
        'quantity' => 100,
    ]);

    $this->token = JWTAuth::fromUser($this->waiter);
});

function integrationHeaders(): array
{
    return [
        'Authorization' => "Bearer " . test()->token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

// ============================================
// Reserva de stock al confirmar pedido
// ============================================

test('al confirmar pedido se reserva stock', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 5000,
        'tax_amount' => 950,
        'discount_amount' => 0,
        'total' => 5950,
    ]);

    $order->items()->create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'menu_item_id' => $this->menuItem->id,
        'name_snapshot' => 'PAPA-001',
        'quantity' => 10,
        'unit_price_snapshot' => 500,
        'subtotal' => 5000,
    ]);

    // Stock antes: 100
    expect($this->inventoryItem->stockForBranch($this->branch->id))->toBe(100.0);

    // Confirmar pedido via API
    $response = $this->withHeaders(integrationHeaders())
        ->postJson("/api/v1/orders/{$order->uuid}/confirm");

    $response->assertOk();

    // Stock despues: 90 (100 - 10 reservados)
    expect($this->inventoryItem->fresh()->stockForBranch($this->branch->id))->toBe(90.0);
});

// ============================================
// Devolucion de stock al cancelar pedido
// ============================================

test('al cancelar pedido confirmado se devuelve stock', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::CONFIRMED,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 7500,
        'tax_amount' => 1425,
        'discount_amount' => 0,
        'total' => 8925,
        'confirmed_at' => now(),
    ]);

    $order->items()->create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'menu_item_id' => $this->menuItem->id,
        'name_snapshot' => 'PAPA-001',
        'quantity' => 15,
        'unit_price_snapshot' => 500,
        'subtotal' => 7500,
    ]);

    // Reservar stock manualmente (simulando confirmacion previa)
    InventoryStock::where('inventory_item_id', $this->inventoryItem->id)
        ->where('branch_id', $this->branch->id)
        ->update(['quantity' => 85]); // 100 - 15

    expect($this->inventoryItem->stockForBranch($this->branch->id))->toBe(85.0);

    // Cancelar pedido
    $response = $this->withHeaders(integrationHeaders())
        ->postJson("/api/v1/orders/{$order->uuid}/cancel", [
            'reason' => 'Cliente canceló',
        ]);

    $response->assertOk();

    // Stock despues: 100 (85 + 15 devueltos)
    expect($this->inventoryItem->fresh()->stockForBranch($this->branch->id))->toBe(100.0);
});

// ============================================
// No se devuelve stock si pedido ya fue servido
// ============================================

test('al cancelar pedido servido NO se devuelve stock', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::SERVED,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
        'confirmed_at' => now()->subMinutes(30),
        'served_at' => now()->subMinutes(10),
    ]);

    $order->items()->create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'menu_item_id' => $this->menuItem->id,
        'name_snapshot' => 'PAPA-001',
        'quantity' => 20,
        'unit_price_snapshot' => 500,
        'subtotal' => 10000,
    ]);

    // Stock ya consumido: 80 (100 - 20)
    InventoryStock::where('inventory_item_id', $this->inventoryItem->id)
        ->where('branch_id', $this->branch->id)
        ->update(['quantity' => 80]);

    expect($this->inventoryItem->stockForBranch($this->branch->id))->toBe(80.0);

    // Intentar cancelar (deberia fallar porque ya esta servido)
    $response = $this->withHeaders(integrationHeaders())
        ->postJson("/api/v1/orders/{$order->uuid}/cancel", [
            'reason' => 'Cliente canceló',
        ]);

    $response->assertStatus(422); // Transicion invalida

    // Stock sigue siendo 80 (no se devuelve)
    expect($this->inventoryItem->fresh()->stockForBranch($this->branch->id))->toBe(80.0);
});
