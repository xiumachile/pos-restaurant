<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Inventory\Domain\Entities\InventoryItem;
use Modules\Inventory\Domain\Entities\InventoryStock;
use Modules\Inventory\Domain\Entities\StockMovement;
use Modules\Inventory\Domain\ValueObjects\StockMovementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'INV-API-' . uniqid(),
        'legal_name' => 'Inventory API Company',
        'trade_name' => 'Inventory API Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'INV-API',
        'name' => 'Inventory API Branch',
    ]);

    $this->adminUser = User::create([
        'name' => 'Inventory Admin',
        'email' => 'inv-admin-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'admin',
    ]);

    $this->token = JWTAuth::fromUser($this->adminUser);
});

function invHeaders(): array
{
    return [
        'Authorization' => "Bearer " . test()->token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

// ============================================
// POST /api/v1/inventory
// ============================================

test('POST /api/v1/inventory crea un item de inventario', function () {
    $response = $this->withHeaders(invHeaders())
        ->postJson('/api/v1/inventory', [
            'sku' => 'PAPA-001',
            'name_translations' => ['es' => 'Papa 1kg', 'zh' => '土豆1公斤'],
            'unit' => 'kg',
            'cost_price' => 1500,
            'min_stock' => 10,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.sku', 'PAPA-001')
        ->assertJsonPath('data.name', 'Papa 1kg')
        ->assertJsonPath('data.unit', 'kg')
        ->assertJsonPath('data.stock', 0)
        ->assertJsonPath('data.status', 'out_of_stock');
});

test('POST /api/v1/inventory requiere nombre en espanol', function () {
    $response = $this->withHeaders(invHeaders())
        ->postJson('/api/v1/inventory', [
            'name_translations' => ['en' => 'Potato'],
            'unit' => 'kg',
        ]);

    $response->assertStatus(422);
});

test('POST /api/v1/inventory valida unidad', function () {
    $response = $this->withHeaders(invHeaders())
        ->postJson('/api/v1/inventory', [
            'name_translations' => ['es' => 'Papa'],
            'unit' => 'invalid_unit',
        ]);

    $response->assertStatus(422);
});

// ============================================
// GET /api/v1/inventory
// ============================================

test('GET /api/v1/inventory lista items del tenant', function () {
    // Crear items
    InventoryItem::create([
        'company_id' => $this->company->id,
        'sku' => 'ITEM-1',
        'name_translations' => ['es' => 'Item 1'],
        'unit' => 'unit',
        'cost_price' => 100,
        'min_stock' => 5,
    ]);

    InventoryItem::create([
        'company_id' => $this->company->id,
        'sku' => 'ITEM-2',
        'name_translations' => ['es' => 'Item 2'],
        'unit' => 'unit',
        'cost_price' => 200,
        'min_stock' => 5,
    ]);

    $response = $this->withHeaders(invHeaders())
        ->getJson('/api/v1/inventory');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

// ============================================
// POST /api/v1/inventory/{uuid}/movement
// ============================================

test('POST /api/v1/inventory/{uuid}/movement registra compra', function () {
    $item = InventoryItem::create([
        'company_id' => $this->company->id,
        'sku' => 'PAPA-001',
        'name_translations' => ['es' => 'Papa 1kg'],
        'unit' => 'kg',
        'cost_price' => 1500,
        'min_stock' => 10,
    ]);

    $response = $this->withHeaders(invHeaders())
        ->postJson("/api/v1/inventory/{$item->uuid}/movement", [
            'branch_uuid' => $this->branch->uuid,
            'type' => 'in_purchase',
            'quantity' => 50,
            'reason' => 'Compra inicial',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'in_purchase')
        ->assertJsonPath('data.quantity', 50)
        ->assertJsonPath('data.balance_after', 50);

    // Verificar que el stock se actualizó
    expect($item->stockForBranch($this->branch->id))->toBe(50.0);
});

test('POST /api/v1/inventory/{uuid}/movement deniega tipo invalido', function () {
    $item = InventoryItem::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Item'],
        'unit' => 'unit',
    ]);

    $response = $this->withHeaders(invHeaders())
        ->postJson("/api/v1/inventory/{$item->uuid}/movement", [
            'branch_uuid' => $this->branch->uuid,
            'type' => 'invalid_type',
            'quantity' => 10,
        ]);

    $response->assertStatus(422);
});

// ============================================
// GET /api/v1/inventory/{uuid}/movements
// ============================================

test('GET /api/v1/inventory/{uuid}/movements retorna historial', function () {
    $item = InventoryItem::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Item'],
        'unit' => 'unit',
    ]);

    // Registrar movimientos
    StockMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        inventoryItemId: $item->id,
        type: StockMovementType::IN_PURCHASE,
        quantity: 100
    );

    StockMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        inventoryItemId: $item->id,
        type: StockMovementType::OUT_RESERVATION,
        quantity: 30
    );

    $response = $this->withHeaders(invHeaders())
        ->getJson("/api/v1/inventory/{$item->uuid}/movements");

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

// ============================================
// GET /api/v1/inventory/alerts
// ============================================

test('GET /api/v1/inventory/alerts retorna items con stock bajo', function () {
    // Item con stock bajo
    $lowItem = InventoryItem::create([
        'company_id' => $this->company->id,
        'sku' => 'LOW-001',
        'name_translations' => ['es' => 'Item Bajo'],
        'unit' => 'unit',
        'min_stock' => 10,
    ]);

    // Item con stock suficiente
    $okItem = InventoryItem::create([
        'company_id' => $this->company->id,
        'sku' => 'OK-001',
        'name_translations' => ['es' => 'Item OK'],
        'unit' => 'unit',
        'min_stock' => 5,
    ]);

    // Dar stock: bajo para lowItem, suficiente para okItem
    InventoryStock::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'inventory_item_id' => $lowItem->id,
        'quantity' => 3, // por debajo de min_stock=10
    ]);

    InventoryStock::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'inventory_item_id' => $okItem->id,
        'quantity' => 50, // por encima de min_stock=5
    ]);

    $response = $this->withHeaders(invHeaders())
        ->getJson('/api/v1/inventory/alerts');

    $response->assertOk()
        ->assertJsonCount(1, 'data') // Solo el item bajo
        ->assertJsonPath('data.0.sku', 'LOW-001');
});

// ============================================
// Autorización
// ============================================

test('sin autenticacion retorna 401', function () {
    $response = $this->getJson('/api/v1/inventory');
    $response->assertStatus(401);
});
