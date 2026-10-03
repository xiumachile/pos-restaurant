<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\Entities\RawIngredientPurchase;
use Modules\Recipes\Domain\ValueObjects\DimensionType;
use Modules\Recipes\Domain\ValueObjects\BaseUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'DOC-' . uniqid(),
        'legal_name' => 'Document Test Company',
        'trade_name' => 'Document Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'DOC',
        'name' => 'Document Branch',
    ]);

    $this->manager = User::create([
        'name' => 'Test Manager',
        'email' => 'doc-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);

    $this->token = JWTAuth::fromUser($this->manager);
});

test('POST /purchase acepta documento contable (factura)', function () {
    $ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'TEST-DOC-' . uniqid(),
        'name_translations' => ['es' => 'Ingrediente doc'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 0,
        'cost_per_base_unit' => 0,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->postJson("/api/v1/recipes/ingredients/{$ingredient->uuid}/purchase", [
        'purchase_unit_name' => 'kg',
        'purchase_quantity' => 10,
        'total_purchase_cost' => 50000,
        'conversion_factor_to_base' => 1000,
        'document_type' => 'factura',
        'document_number' => 'F-12345',
        'supplier_name' => 'Distribuidora Central',
        'supplier_rut' => '76.123.456-7',
    ]);

    $response->assertStatus(201);

    $purchase = RawIngredientPurchase::first();
    expect($purchase->document_type)->toBe('factura');
    expect($purchase->document_number)->toBe('F-12345');
    expect($purchase->supplier_name)->toBe('Distribuidora Central');
    expect($purchase->supplier_rut)->toBe('76.123.456-7');
});

test('POST /purchase acepta documento boleta', function () {
    $ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'TEST-BOL-' . uniqid(),
        'name_translations' => ['es' => 'Ingrediente boleta'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 0,
        'cost_per_base_unit' => 0,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->postJson("/api/v1/recipes/ingredients/{$ingredient->uuid}/purchase", [
        'purchase_unit_name' => 'kg',
        'purchase_quantity' => 5,
        'total_purchase_cost' => 25000,
        'conversion_factor_to_base' => 1000,
        'document_type' => 'boleta',
        'document_number' => 'B-98765',
    ]);

    $response->assertStatus(201);

    $purchase = RawIngredientPurchase::first();
    expect($purchase->document_type)->toBe('boleta');
    expect($purchase->document_number)->toBe('B-98765');
});

test('POST /purchase valida tipo de documento', function () {
    $ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'TEST-VAL-' . uniqid(),
        'name_translations' => ['es' => 'Ingrediente val'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 0,
        'cost_per_base_unit' => 0,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->postJson("/api/v1/recipes/ingredients/{$ingredient->uuid}/purchase", [
        'purchase_unit_name' => 'kg',
        'purchase_quantity' => 5,
        'total_purchase_cost' => 25000,
        'conversion_factor_to_base' => 1000,
        'document_type' => 'tipo_invalido',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['document_type']);
});

test('POST /purchase funciona sin documento (opcional)', function () {
    $ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'TEST-NODOC-' . uniqid(),
        'name_translations' => ['es' => 'Ingrediente nodoc'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 0,
        'cost_per_base_unit' => 0,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->postJson("/api/v1/recipes/ingredients/{$ingredient->uuid}/purchase", [
        'purchase_unit_name' => 'kg',
        'purchase_quantity' => 3,
        'total_purchase_cost' => 15000,
        'conversion_factor_to_base' => 1000,
    ]);

    $response->assertStatus(201);

    $purchase = RawIngredientPurchase::first();
    expect($purchase->document_type)->toBeNull();
    expect($purchase->document_number)->toBeNull();
});
