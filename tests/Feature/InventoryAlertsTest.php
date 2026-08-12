<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Inventory\Domain\Entities\InventoryItem;
use Modules\Inventory\Domain\Entities\InventoryStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'ALERT-' . uniqid(),
        'legal_name' => 'Alerts Test Company',
        'trade_name' => 'Alerts Test Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'ALERT',
        'name' => 'Alerts Branch',
    ]);

    $this->manager = User::create([
        'name' => 'Alerts Manager',
        'email' => 'alerts-mgr-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);

    $this->token = JWTAuth::fromUser($this->manager);
});

function alertsHeaders(): array
{
    return [
        'Authorization' => "Bearer " . test()->token,
        'Accept' => 'application/json',
    ];
}

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

    $response = $this->withHeaders(alertsHeaders())
        ->getJson('/api/v1/inventory/alerts');

    $response->assertOk()
        ->assertJsonCount(1, 'data') // Solo el item bajo
        ->assertJsonPath('data.0.sku', 'LOW-001')
        ->assertJsonPath('data.0.status', 'low_stock');
});

test('GET /api/v1/inventory/alerts retorna items sin stock', function () {
    // Item sin stock
    $outItem = InventoryItem::create([
        'company_id' => $this->company->id,
        'sku' => 'OUT-001',
        'name_translations' => ['es' => 'Item Sin Stock'],
        'unit' => 'unit',
        'min_stock' => 5,
    ]);

    // Crear registro de stock con cantidad 0
    InventoryStock::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'inventory_item_id' => $outItem->id,
        'quantity' => 0,
    ]);

    $response = $this->withHeaders(alertsHeaders())
        ->getJson('/api/v1/inventory/alerts');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.sku', 'OUT-001')
        ->assertJsonPath('data.0.status', 'out_of_stock');
});

test('GET /api/v1/inventory/alerts retorna array vacio si no hay alertas', function () {
    // Item con stock suficiente
    $okItem = InventoryItem::create([
        'company_id' => $this->company->id,
        'sku' => 'OK-001',
        'name_translations' => ['es' => 'Item OK'],
        'unit' => 'unit',
        'min_stock' => 5,
    ]);

    InventoryStock::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'inventory_item_id' => $okItem->id,
        'quantity' => 50,
    ]);

    $response = $this->withHeaders(alertsHeaders())
        ->getJson('/api/v1/inventory/alerts');

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});

test('GET /api/v1/inventory/alerts requiere autenticacion', function () {
    $response = $this->getJson('/api/v1/inventory/alerts');
    $response->assertStatus(401);
});

test('GET /api/v1/inventory/alerts solo muestra items de la sucursal del usuario', function () {
    $otherBranch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'OTHER',
        'name' => 'Other Branch',
    ]);

    // Item con stock bajo en la sucursal del usuario
    $lowItem = InventoryItem::create([
        'company_id' => $this->company->id,
        'sku' => 'LOW-001',
        'name_translations' => ['es' => 'Item Bajo'],
        'unit' => 'unit',
        'min_stock' => 10,
    ]);

    // Item con stock bajo en OTRA sucursal
    $otherLowItem = InventoryItem::create([
        'company_id' => $this->company->id,
        'sku' => 'OTHER-LOW',
        'name_translations' => ['es' => 'Item Bajo Otra'],
        'unit' => 'unit',
        'min_stock' => 10,
    ]);

    InventoryStock::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'inventory_item_id' => $lowItem->id,
        'quantity' => 3,
    ]);

    InventoryStock::create([
        'company_id' => $this->company->id,
        'branch_id' => $otherBranch->id,
        'inventory_item_id' => $otherLowItem->id,
        'quantity' => 2,
    ]);

    $response = $this->withHeaders(alertsHeaders())
        ->getJson('/api/v1/inventory/alerts');

    $response->assertOk()
        ->assertJsonCount(1, 'data') // Solo el de la sucursal del usuario
        ->assertJsonPath('data.0.sku', 'LOW-001');
});
