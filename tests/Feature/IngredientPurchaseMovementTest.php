<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\Entities\RawIngredientMovement;
use Modules\Recipes\Domain\Entities\RawIngredientPurchase;
use Modules\Recipes\Domain\ValueObjects\DimensionType;
use Modules\Recipes\Domain\ValueObjects\BaseUnit;
use Modules\Recipes\Domain\ValueObjects\MovementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'PURCH-' . uniqid(),
        'legal_name' => 'Purchase Test Company',
        'trade_name' => 'Purchase Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'PURCH',
        'name' => 'Purchase Branch',
    ]);

    $this->manager = User::create([
        'name' => 'Test Manager',
        'email' => 'purch-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);

    $this->token = JWTAuth::fromUser($this->manager);
});

test('POST /purchase crea movimiento in_purchase en el historial', function () {
    // Crear insumo con stock inicial
    $ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'TEST-PURCH-' . uniqid(),
        'name_translations' => ['es' => 'Ingrediente de prueba'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 1000,
        'cost_per_base_unit' => 5.0,
    ]);

    $initialMovements = RawIngredientMovement::count();

    // Registrar compra vía endpoint
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ])->postJson("/api/v1/recipes/ingredients/{$ingredient->uuid}/purchase", [
        'purchase_unit_name' => 'kg',
        'purchase_quantity' => 5, // 5 kg
        'total_purchase_cost' => 25000, // CLP 25000
        'conversion_factor_to_base' => 1000, // 1 kg = 1000 g
    ]);

    $response->assertStatus(201);

    // Verificar que se creó un movimiento nuevo
    expect(RawIngredientMovement::count())->toBe($initialMovements + 1);

    // Verificar que el movimiento es del tipo correcto
    $movement = RawIngredientMovement::latest('id')->first();
    expect($movement->type)->toBe(MovementType::InPurchase);
    expect((float) $movement->quantity_base)->toBe(5000.0); // 5 kg = 5000 g
    expect($movement->reference_type)->toBe('purchase');
    expect($movement->raw_ingredient_id)->toBe($ingredient->id);

    // Verificar que el stock se actualizó
    $ingredient->refresh();
    expect((float) $ingredient->current_stock_base)->toBe(6000.0); // 1000 + 5000
});

test('POST /purchase actualiza costo promedio ponderado', function () {
    // Crear insumo con costo inicial
    $ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'TEST-COST-' . uniqid(),
        'name_translations' => ['es' => 'Ingrediente costo'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 1000,
        'cost_per_base_unit' => 10.0, // CLP 10/g
    ]);

    // Comprar 1000g a CLP 5/g (más barato)
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->postJson("/api/v1/recipes/ingredients/{$ingredient->uuid}/purchase", [
        'purchase_unit_name' => 'kg',
        'purchase_quantity' => 1,
        'total_purchase_cost' => 5000, // CLP 5000 por 1000g = 5/g
        'conversion_factor_to_base' => 1000,
    ]);

    $response->assertStatus(201);

    // Costo promedio: (1000*10 + 1000*5) / 2000 = 7.5/g
    $ingredient->refresh();
    expect((float) $ingredient->cost_per_base_unit)->toBe(7.5);
});
