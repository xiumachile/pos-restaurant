<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\Entities\RawIngredientMovement;
use Modules\Recipes\Domain\ValueObjects\MovementType;
use Modules\Recipes\Domain\ValueObjects\DimensionType;
use Modules\Recipes\Domain\ValueObjects\BaseUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'MOV-' . uniqid(),
        'legal_name' => 'Movement Test Company',
        'trade_name' => 'Movement Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'MOV',
        'name' => 'Movement Branch',
    ]);

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'mov-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);
});

function createIngredient($test, float $initialStock = 0): RawIngredient
{
    return RawIngredient::create([
        'company_id' => $test->company->id,
        'branch_id' => $test->branch->id,
        'sku' => 'SKU-' . uniqid(),
        'name_translations' => ['es' => 'Harina', 'en' => 'Flour'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => $initialStock,
        'minimum_stock_base' => 1000,
        'cost_per_base_unit' => 2.5,
        'is_active' => true,
    ]);
}

// ============================================
// Creación de movimientos
// ============================================

test('se puede crear un movimiento de compra', function () {
    $ingredient = createIngredient($this, 0);

    $movement = RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::InPurchase,
        quantityBase: 5000,
        userId: $this->user->id,
        reason: 'Compra inicial de harina'
    );

    expect($movement->id)->not->toBeNull();
    expect($movement->uuid)->not->toBeNull();
    expect($movement->type)->toBe(MovementType::InPurchase);
    expect($movement->quantity_base)->toBe(5000.0);
    expect($movement->balance_after)->toBe(5000.0);
    expect($movement->user_id)->toBe($this->user->id);
    expect($movement->reason)->toBe('Compra inicial de harina');
});

test('se puede crear un movimiento de consumo', function () {
    $ingredient = createIngredient($this, 5000);

    $movement = RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::OutConsumption,
        quantityBase: 1500,
        userId: $this->user->id,
        reason: 'Preparación de pedido #123'
    );

    expect($movement->type)->toBe(MovementType::OutConsumption);
    expect($movement->quantity_base)->toBe(1500.0);
    expect($movement->balance_after)->toBe(3500.0); // 5000 - 1500
});

test('se puede crear un movimiento de ajuste', function () {
    $ingredient = createIngredient($this, 3000);

    $movement = RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::Adjustment,
        quantityBase: -200, // Ajuste negativo (merma/inventario)
        userId: $this->user->id,
        reason: 'Ajuste de inventario por merma'
    );

    expect($movement->type)->toBe(MovementType::Adjustment);
    expect($movement->quantity_base)->toBe(-200.0);
    expect($movement->balance_after)->toBe(2800.0); // 3000 - 200
});

// ============================================
// Cálculo de balance_after
// ============================================

test('balance_after se calcula correctamente para movimientos de entrada', function () {
    $ingredient = createIngredient($this, 1000);

    $movement = RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::InPurchase,
        quantityBase: 2000,
        userId: $this->user->id
    );

    expect($movement->balance_after)->toBe(3000.0); // 1000 + 2000
});

test('balance_after se calcula correctamente para movimientos de salida', function () {
    $ingredient = createIngredient($this, 5000);

    $movement = RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::OutConsumption,
        quantityBase: 1800,
        userId: $this->user->id
    );

    expect($movement->balance_after)->toBe(3200.0); // 5000 - 1800
});

test('balance_after se calcula correctamente para ajustes positivos', function () {
    $ingredient = createIngredient($this, 2500);

    $movement = RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::Adjustment,
        quantityBase: 500, // Ajuste positivo
        userId: $this->user->id
    );

    expect($movement->balance_after)->toBe(3000.0); // 2500 + 500
});

// ============================================
// Actualización de current_stock_base
// ============================================

test('current_stock_base del ingrediente se actualiza después de un movimiento', function () {
    $ingredient = createIngredient($this, 0);

    RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::InPurchase,
        quantityBase: 3000,
        userId: $this->user->id
    );

    $ingredient->refresh();
    expect($ingredient->current_stock_base)->toBe(3000.0);
});

test('current_stock_base se actualiza correctamente después de múltiples movimientos', function () {
    $ingredient = createIngredient($this, 1000);

    RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::InPurchase,
        quantityBase: 2000,
        userId: $this->user->id
    );

    RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::OutConsumption,
        quantityBase: 800,
        userId: $this->user->id
    );

    RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::Adjustment,
        quantityBase: -100,
        userId: $this->user->id
    );

    $ingredient->refresh();
    expect($ingredient->current_stock_base)->toBe(2100.0); // 1000 + 2000 - 800 - 100
});

// ============================================
// Relaciones polimórficas
// ============================================

test('movimiento puede tener reference_type y reference_id', function () {
    $ingredient = createIngredient($this, 0);

    $movement = RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::OutConsumption,
        quantityBase: 500,
        referenceType: 'order',
        referenceId: 123,
        userId: $this->user->id
    );

    expect($movement->reference_type)->toBe('order');
    expect($movement->reference_id)->toBe(123);
});

test('movimiento puede tener reference_type null', function () {
    $ingredient = createIngredient($this, 0);

    $movement = RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::Adjustment,
        quantityBase: 100,
        userId: $this->user->id
    );

    expect($movement->reference_type)->toBeNull();
    expect($movement->reference_id)->toBeNull();
});

// ============================================
// Validaciones
// ============================================

test('no se puede crear movimiento con quantity_base zero', function () {
    $ingredient = createIngredient($this, 1000);

    $this->expectException(\InvalidArgumentException::class);

    RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::Adjustment,
        quantityBase: 0,
        userId: $this->user->id
    );
});

test('no se puede crear movimiento de salida si no hay stock suficiente', function () {
    $ingredient = createIngredient($this, 500);

    $this->expectException(\Modules\Recipes\Domain\Exceptions\InsufficientStockException::class);

    RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::OutConsumption,
        quantityBase: 1000, // Más que el stock disponible
        userId: $this->user->id
    );
});

// ============================================
// Scopes y queries
// ============================================

test('scope forIngredient filtra movimientos por ingrediente', function () {
    $ingredient1 = createIngredient($this, 0);
    $ingredient2 = createIngredient($this, 0);

    RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient1->id,
        type: MovementType::InPurchase,
        quantityBase: 1000,
        userId: $this->user->id
    );

    RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient2->id,
        type: MovementType::InPurchase,
        quantityBase: 2000,
        userId: $this->user->id
    );

    $movements = RawIngredientMovement::forIngredient($ingredient1->id)->get();
    expect($movements)->toHaveCount(1);
    expect($movements->first()->raw_ingredient_id)->toBe($ingredient1->id);
});

test('scope forBranch filtra movimientos por sucursal', function () {
    $branch2 = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'MOV2',
        'name' => 'Movement Branch 2',
    ]);

    $ingredient1 = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'SKU-B1',
        'name_translations' => ['es' => 'Ingrediente B1'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 0,
        'minimum_stock_base' => 0,
        'cost_per_base_unit' => 1,
        'is_active' => true,
    ]);

    $ingredient2 = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $branch2->id,
        'sku' => 'SKU-B2',
        'name_translations' => ['es' => 'Ingrediente B2'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 0,
        'minimum_stock_base' => 0,
        'cost_per_base_unit' => 1,
        'is_active' => true,
    ]);

    RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient1->id,
        type: MovementType::InPurchase,
        quantityBase: 1000,
        userId: $this->user->id
    );

    RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $branch2->id,
        rawIngredientId: $ingredient2->id,
        type: MovementType::InPurchase,
        quantityBase: 2000,
        userId: $this->user->id
    );

    $movements = RawIngredientMovement::forBranch($this->branch->id)->get();
    expect($movements)->toHaveCount(1);
    expect($movements->first()->branch_id)->toBe($this->branch->id);
});
