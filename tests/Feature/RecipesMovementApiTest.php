<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\Entities\RawIngredientMovement;
use Modules\Recipes\Domain\ValueObjects\DimensionType;
use Modules\Recipes\Domain\ValueObjects\BaseUnit;
use Modules\Recipes\Domain\ValueObjects\MovementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'MOV-API-' . uniqid(),
        'legal_name' => 'Movement API Company',
        'trade_name' => 'Movement API',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'MOV-API',
        'name' => 'Movement API Branch',
    ]);

    $this->manager = User::create([
        'name' => 'Test Manager',
        'email' => 'movapi-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);

    $this->token = JWTAuth::fromUser($this->manager);
});

function movHeaders(): array
{
    return [
        'Authorization' => 'Bearer ' . test()->token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

function createTestIngredient($test, float $initialStock = 0): RawIngredient
{
    return RawIngredient::create([
        'company_id' => $test->company->id,
        'branch_id' => $test->branch->id,
        'sku' => 'SKU-' . uniqid(),
        'name_translations' => ['es' => 'Harina'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => $initialStock,
        'minimum_stock_base' => 1000,
        'cost_per_base_unit' => 2.5,
        'is_active' => true,
    ]);
}

// ============================================
// POST /ingredients/{uuid}/movements
// ============================================

test('POST /movements registra un movimiento de compra', function () {
    $ingredient = createTestIngredient($this, 0);

    $response = $this->withHeaders(movHeaders())
        ->postJson("/api/v1/recipes/ingredients/{$ingredient->uuid}/movements", [
            'type' => 'in_purchase',
            'quantity_base' => 5000,
            'reason' => 'Compra inicial',
        ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'data' => ['uuid', 'type', 'quantity_base', 'balance_after', 'reason', 'created_at'],
    ]);
    $response->assertJsonPath('data.type', 'in_purchase');
    expect((float) $response->json('data.balance_after'))->toBe(5000.0);

    // Stock del ingrediente actualizado
    $ingredient->refresh();
    expect((float) $ingredient->current_stock_base)->toBe(5000.0);
});

test('POST /movements registra un movimiento de consumo', function () {
    $ingredient = createTestIngredient($this, 5000);

    $response = $this->withHeaders(movHeaders())
        ->postJson("/api/v1/recipes/ingredients/{$ingredient->uuid}/movements", [
            'type' => 'out_consumption',
            'quantity_base' => 1500,
            'reason' => 'Consumo de pedido',
            'reference_type' => 'order',
            'reference_id' => 123,
        ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.type', 'out_consumption');
    expect((float) $response->json('data.balance_after'))->toBe(3500.0);
    expect($response->json('data.reference_type'))->toBe('order');
    expect($response->json('data.reference_id'))->toBe(123);
});

test('POST /movements registra un ajuste positivo', function () {
    $ingredient = createTestIngredient($this, 1000);

    $response = $this->withHeaders(movHeaders())
        ->postJson("/api/v1/recipes/ingredients/{$ingredient->uuid}/movements", [
            'type' => 'adjustment',
            'quantity_base' => 200,
            'reason' => 'Ajuste de inventario',
        ]);

    $response->assertStatus(201);
    expect((float) $response->json('data.balance_after'))->toBe(1200.0);
});

test('POST /movements registra un ajuste negativo', function () {
    $ingredient = createTestIngredient($this, 1000);

    $response = $this->withHeaders(movHeaders())
        ->postJson("/api/v1/recipes/ingredients/{$ingredient->uuid}/movements", [
            'type' => 'adjustment',
            'quantity_base' => -150,
            'reason' => 'Merma detectada',
        ]);

    $response->assertStatus(201);
    expect((float) $response->json('data.balance_after'))->toBe(850.0);
});

test('POST /movements falla con 422 si quantity_base es zero', function () {
    $ingredient = createTestIngredient($this, 1000);

    $response = $this->withHeaders(movHeaders())
        ->postJson("/api/v1/recipes/ingredients/{$ingredient->uuid}/movements", [
            'type' => 'adjustment',
            'quantity_base' => 0,
        ]);

    $response->assertStatus(422);
});

test('POST /movements falla con 422 si type es inválido', function () {
    $ingredient = createTestIngredient($this, 1000);

    $response = $this->withHeaders(movHeaders())
        ->postJson("/api/v1/recipes/ingredients/{$ingredient->uuid}/movements", [
            'type' => 'invalid_type',
            'quantity_base' => 100,
        ]);

    $response->assertStatus(422);
});

test('POST /movements falla con 409 si no hay stock suficiente', function () {
    $ingredient = createTestIngredient($this, 500);

    $response = $this->withHeaders(movHeaders())
        ->postJson("/api/v1/recipes/ingredients/{$ingredient->uuid}/movements", [
            'type' => 'out_consumption',
            'quantity_base' => 1000, // más que el stock disponible
        ]);

    $response->assertStatus(409);
    $response->assertJsonPath('error', 'insufficient_stock');
});

test('POST /movements falla con 404 si ingrediente no existe', function () {
    $response = $this->withHeaders(movHeaders())
        ->postJson('/api/v1/recipes/ingredients/00000000-0000-0000-0000-000000000000/movements', [
            'type' => 'in_purchase',
            'quantity_base' => 100,
        ]);

    $response->assertStatus(404);
});

test('POST /movements falla con 404 si ingrediente pertenece a otro tenant', function () {
    $otherCompany = Company::create([
        'tax_id' => 'OTHER-' . uniqid(),
        'legal_name' => 'Other Company',
        'trade_name' => 'Other',
    ]);
    $otherBranch = Branch::create([
        'company_id' => $otherCompany->id,
        'code' => 'OTH',
        'name' => 'Other Branch',
    ]);
    $otherIngredient = RawIngredient::create([
        'company_id' => $otherCompany->id,
        'branch_id' => $otherBranch->id,
        'sku' => 'SKU-OTHER',
        'name_translations' => ['es' => 'Ajeno'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 0,
        'minimum_stock_base' => 0,
        'cost_per_base_unit' => 1,
        'is_active' => true,
    ]);

    $response = $this->withHeaders(movHeaders())
        ->postJson("/api/v1/recipes/ingredients/{$otherIngredient->uuid}/movements", [
            'type' => 'in_purchase',
            'quantity_base' => 100,
        ]);

    $response->assertStatus(404);
});

test('POST /movements requiere autenticación', function () {
    $ingredient = createTestIngredient($this, 0);

    $response = $this->postJson("/api/v1/recipes/ingredients/{$ingredient->uuid}/movements", [
        'type' => 'in_purchase',
        'quantity_base' => 100,
    ]);

    $response->assertStatus(401);
});

// ============================================
// GET /ingredients/{uuid}/movements
// ============================================

test('GET /movements lista los movimientos de un ingrediente', function () {
    $ingredient = createTestIngredient($this, 0);

    // Crear 3 movimientos
    RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::InPurchase,
        quantityBase: 1000,
        userId: $this->manager->id
    );
    RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::OutConsumption,
        quantityBase: 200,
        userId: $this->manager->id
    );
    RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::Adjustment,
        quantityBase: -50,
        userId: $this->manager->id
    );

    $response = $this->withHeaders(movHeaders())
        ->getJson("/api/v1/recipes/ingredients/{$ingredient->uuid}/movements");

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'data');

    // Verificar orden descendente por fecha
    $movements = $response->json('data');
    expect($movements[0]['type'])->toBe('adjustment');
    expect($movements[2]['type'])->toBe('in_purchase');
});

test('GET /movements filtra por type', function () {
    $ingredient = createTestIngredient($this, 0);

    RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::InPurchase,
        quantityBase: 1000,
        userId: $this->manager->id
    );
    RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $ingredient->id,
        type: MovementType::OutConsumption,
        quantityBase: 200,
        userId: $this->manager->id
    );

    $response = $this->withHeaders(movHeaders())
        ->getJson("/api/v1/recipes/ingredients/{$ingredient->uuid}/movements?type=in_purchase");

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.type'))->toBe('in_purchase');
});

test('GET /movements falla con 404 si ingrediente no pertenece al tenant', function () {
    $otherCompany = Company::create([
        'tax_id' => 'OTH2-' . uniqid(),
        'legal_name' => 'Other 2',
        'trade_name' => 'Other 2',
    ]);
    $otherBranch = Branch::create([
        'company_id' => $otherCompany->id,
        'code' => 'OTH2',
        'name' => 'Other Branch 2',
    ]);
    $otherIngredient = RawIngredient::create([
        'company_id' => $otherCompany->id,
        'branch_id' => $otherBranch->id,
        'sku' => 'SKU-OTH2',
        'name_translations' => ['es' => 'Ajeno 2'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 0,
        'minimum_stock_base' => 0,
        'cost_per_base_unit' => 1,
        'is_active' => true,
    ]);

    $response = $this->withHeaders(movHeaders())
        ->getJson("/api/v1/recipes/ingredients/{$otherIngredient->uuid}/movements");

    $response->assertStatus(404);
});
