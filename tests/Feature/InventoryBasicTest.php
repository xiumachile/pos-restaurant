<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Inventory\Domain\Entities\InventoryItem;
use Modules\Inventory\Domain\Entities\InventoryStock;
use Modules\Inventory\Domain\Entities\StockMovement;
use Modules\Inventory\Domain\ValueObjects\StockMovementType;
use Modules\Inventory\Domain\ValueObjects\StockStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'INV-' . uniqid(),
        'legal_name' => 'Inventory Test Company',
        'trade_name' => 'Inventory Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'INV',
        'name' => 'Inventory Branch',
    ]);
});

function createInvItem($test, float $minStock = 5): InventoryItem
{
    return InventoryItem::create([
        'company_id' => $test->company->id,
        'sku' => 'SKU-' . uniqid(),
        'name_translations' => ['es' => 'Papas 1kg', 'zh' => '土豆1公斤'],
        'unit' => 'kg',
        'cost_price' => 1500,
        'min_stock' => $minStock,
        'is_active' => true,
    ]);
}

// ============================================
// InventoryItem basics
// ============================================

test('se puede crear un item de inventario', function () {
    $item = createInvItem($this);

    expect($item->id)->not->toBeNull();
    expect($item->uuid)->not->toBeNull();
    expect($item->name_translations['es'])->toBe('Papas 1kg');
    expect($item->unit)->toBe('kg');
});

test('item nuevo tiene stock 0 en cualquier sucursal', function () {
    $item = createInvItem($this);

    expect($item->stockForBranch($this->branch->id))->toBe(0.0);
});

test('stock status es OUT_OF_STOCK cuando no hay stock', function () {
    $item = createInvItem($this);

    expect($item->stockStatusForBranch($this->branch->id))->toBe(StockStatus::OUT_OF_STOCK);
});

// ============================================
// StockMovement::record
// ============================================

test('movimiento de compra incrementa el stock', function () {
    $item = createInvItem($this);

    StockMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        inventoryItemId: $item->id,
        type: StockMovementType::IN_PURCHASE,
        quantity: 50,
        referenceType: 'purchase',
        referenceId: 1,
        reason: 'Compra inicial'
    );

    expect($item->stockForBranch($this->branch->id))->toBe(50.0);
});

test('movimiento de reserva decrementa el stock', function () {
    $item = createInvItem($this);

    // Compra inicial
    StockMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        inventoryItemId: $item->id,
        type: StockMovementType::IN_PURCHASE,
        quantity: 50
    );

    // Reserva
    StockMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        inventoryItemId: $item->id,
        type: StockMovementType::OUT_RESERVATION,
        quantity: 10,
        referenceType: 'order',
        referenceId: 1
    );

    expect($item->stockForBranch($this->branch->id))->toBe(40.0);
});

test('balance_after se calcula correctamente', function () {
    $item = createInvItem($this);

    $movement = StockMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        inventoryItemId: $item->id,
        type: StockMovementType::IN_PURCHASE,
        quantity: 25
    );

    expect((float) $movement->balance_after)->toBe(25.0);
});

test('multiples movimientos mantienen balance correcto', function () {
    $item = createInvItem($this);

    // +100 compra
    StockMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        inventoryItemId: $item->id,
        type: StockMovementType::IN_PURCHASE,
        quantity: 100
    );

    // -30 reserva
    StockMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        inventoryItemId: $item->id,
        type: StockMovementType::OUT_RESERVATION,
        quantity: 30
    );

    // +5 devolucion
    StockMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        inventoryItemId: $item->id,
        type: StockMovementType::IN_RETURN,
        quantity: 5
    );

    // 100 - 30 + 5 = 75
    expect($item->stockForBranch($this->branch->id))->toBe(75.0);
});

// ============================================
// StockStatus
// ============================================

test('status es LOW_STOCK cuando el stock esta por debajo del minimo', function () {
    $item = createInvItem($this, minStock: 10);

    StockMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        inventoryItemId: $item->id,
        type: StockMovementType::IN_PURCHASE,
        quantity: 8 // por debajo de min_stock=10
    );

    expect($item->stockStatusForBranch($this->branch->id))->toBe(StockStatus::LOW_STOCK);
});

test('status es AVAILABLE cuando el stock es suficiente', function () {
    $item = createInvItem($this, minStock: 10);

    StockMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        inventoryItemId: $item->id,
        type: StockMovementType::IN_PURCHASE,
        quantity: 50
    );

    expect($item->stockStatusForBranch($this->branch->id))->toBe(StockStatus::AVAILABLE);
});

test('stock es independiente por sucursal', function () {
    $otherBranch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'INV-2',
        'name' => 'Second Branch',
    ]);

    $item = createInvItem($this);

    // Stock solo en la primera sucursal
    StockMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        inventoryItemId: $item->id,
        type: StockMovementType::IN_PURCHASE,
        quantity: 50
    );

    expect($item->stockForBranch($this->branch->id))->toBe(50.0);
    expect($item->stockForBranch($otherBranch->id))->toBe(0.0);
});
