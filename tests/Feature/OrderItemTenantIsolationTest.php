<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Domain\Entities\MenuItem;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Tables\Domain\Entities\RestaurantTable;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Empresa A (Sucursal A1)
    $this->companyA = Company::create(['tax_id' => 'A', 'legal_name' => 'Company A', 'trade_name' => 'Comp A']);
    $this->branchA1 = Branch::create(['company_id' => $this->companyA->id, 'code' => 'A1', 'name' => 'Branch A1']);
    
    // Empresa B (Sucursal B1)
    $this->companyB = Company::create(['tax_id' => 'B', 'legal_name' => 'Company B', 'trade_name' => 'Comp B']);
    $this->branchB1 = Branch::create(['company_id' => $this->companyB->id, 'code' => 'B1', 'name' => 'Branch B1']);

    // Usuarios
    $this->waiterA = User::create(['name' => 'Waiter A', 'email' => 'wa@a.test', 'password' => bcrypt('pw'), 'company_id' => $this->companyA->id, 'branch_id' => $this->branchA1->id, 'role' => 'waiter']);
    $this->waiterB = User::create(['name' => 'Waiter B', 'email' => 'wb@b.test', 'password' => bcrypt('pw'), 'company_id' => $this->companyB->id, 'branch_id' => $this->branchB1->id, 'role' => 'waiter']);

    // Productos
    $this->productA = Product::create(['company_id' => $this->companyA->id, 'branch_id' => $this->branchA1->id, 'name_translations' => ['es' => 'Prod A'], 'base_price' => 1000, 'is_active' => true]);
    $this->productB = Product::create(['company_id' => $this->companyB->id, 'branch_id' => $this->branchB1->id, 'name_translations' => ['es' => 'Prod B'], 'base_price' => 2000, 'is_active' => true]);
    $this->productA_inactive = Product::create(['company_id' => $this->companyA->id, 'branch_id' => $this->branchA1->id, 'name_translations' => ['es' => 'Prod A Inactivo'], 'base_price' => 1000, 'is_active' => false]);

    // Menu Items
    $this->menuItemA = MenuItem::create(['company_id' => $this->companyA->id, 'branch_id' => $this->branchA1->id, 'product_id' => $this->productA->id, 'base_price' => 1000, 'is_active' => true]);
    $this->menuItemB = MenuItem::create(['company_id' => $this->companyB->id, 'branch_id' => $this->branchB1->id, 'product_id' => $this->productB->id, 'base_price' => 2000, 'is_active' => true]);

    // Pedido en Empresa A
    $this->tableA = RestaurantTable::create(['company_id' => $this->companyA->id, 'branch_id' => $this->branchA1->id, 'table_number' => '1', 'capacity' => 2, 'area_code' => 'MAIN', 'area_name_translations' => ['es' => 'Salón']]);
    $this->orderA = Order::create(['company_id' => $this->companyA->id, 'branch_id' => $this->branchA1->id, 'waiter_id' => $this->waiterA->id, 'order_number' => 'ORD-A-1', 'type' => OrderType::DINE_IN, 'status' => 'draft', 'subtotal' => 0, 'tax_amount' => 0, 'total' => 0]);

    $this->tokenA = JWTAuth::fromUser($this->waiterA);
});

function headersA($token): array {
    return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json', 'Content-Type' => 'application/json'];
}

test('M-01: Usuario de sucursal A NO puede agregar producto de sucursal B por product_uuid', function () {
    $response = $this->withHeaders(headersA($this->tokenA))
        ->postJson("/api/v1/orders/{$this->orderA->uuid}/items", [
            'product_uuid' => $this->productB->uuid,
            'quantity' => 1,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'product_not_found');
    
    expect($this->orderA->items()->count())->toBe(0);
});

test('M-01: Usuario de sucursal A NO puede agregar menu_item de sucursal B por menu_item_uuid', function () {
    $response = $this->withHeaders(headersA($this->tokenA))
        ->postJson("/api/v1/orders/{$this->orderA->uuid}/items", [
            'menu_item_uuid' => $this->menuItemB->uuid,
            'quantity' => 1,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'product_not_found');
    
    expect($this->orderA->items()->count())->toBe(0);
});

test('M-01: Usuario NO puede agregar producto inactivo de su propia sucursal', function () {
    $response = $this->withHeaders(headersA($this->tokenA))
        ->postJson("/api/v1/orders/{$this->orderA->uuid}/items", [
            'product_uuid' => $this->productA_inactive->uuid,
            'quantity' => 1,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'product_not_found');
    
    expect($this->orderA->items()->count())->toBe(0);
});

test('M-01: Usuario SÍ puede agregar producto válido de su propia sucursal', function () {
    $response = $this->withHeaders(headersA($this->tokenA))
        ->postJson("/api/v1/orders/{$this->orderA->uuid}/items", [
            'menu_item_uuid' => $this->menuItemA->uuid,
            'quantity' => 2,
        ]);

    $response->assertStatus(201);
    expect($this->orderA->fresh()->items()->count())->toBe(1)
        ->and($this->orderA->fresh()->items()->first()->unit_price_snapshot)->toBe(1000)
        ->and($this->orderA->fresh()->total)->toBe(2000);
});
