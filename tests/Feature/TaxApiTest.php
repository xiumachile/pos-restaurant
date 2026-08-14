<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Tax\Domain\Entities\Tax;
use Modules\Tax\Domain\ValueObjects\TaxType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::forceCreate([
        'tax_id' => '76.123.456-7',
        'legal_name' => 'Tax API Test SpA',
        'trade_name' => 'Tax API Test',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'TAPI',
        'name' => 'Tax API Branch',
    ]);

    $this->cashier = User::forceCreate([
        'name' => 'Cashier',
        'email' => 'cashier-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    $this->manager = User::forceCreate([
        'name' => 'Manager',
        'email' => 'manager-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);

    $this->cashierToken = JWTAuth::fromUser($this->cashier);
    $this->managerToken = JWTAuth::fromUser($this->manager);

    // Tax default
    $this->iva19 = Tax::create([
        'company_id' => $this->company->id,
        'name' => 'IVA 19%',
        'code' => 'IVA',
        'type' => TaxType::PERCENT,
        'rate' => 19.00,
        'is_default' => true,
        'is_active' => true,
    ]);
});

function taxHeaders($useManager = false): array
{
    $token = $useManager ? test()->managerToken : test()->cashierToken;
    return [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

// ============================================
// Autorización
// ============================================

test('GET /api/v1/taxes sin auth retorna 401', function () {
    $this->getJson('/api/v1/taxes')->assertStatus(401);
});

test('POST /api/v1/taxes solo permite a manager/admin', function () {
    // Cashier no puede
    $response = $this->actingAs($this->cashier, 'api')
        ->postJson('/api/v1/taxes', [
            'name' => 'Test',
            'code' => 'T1',
            'type' => 'percent',
            'rate' => 10,
        ]);
    expect(in_array($response->status(), [401, 403]))->toBeTrue();

    // Manager sí puede
    $response = $this->actingAs($this->manager, 'api')
        ->postJson('/api/v1/taxes', [
            'name' => 'IVA Reducido',
            'code' => 'IVA-R',
            'type' => 'percent',
            'rate' => 10,
        ]);
    $response->assertStatus(201);
});

// ============================================
// GET /api/v1/taxes (index)
// ============================================

test('GET /api/v1/taxes lista impuestos de la empresa', function () {
    Tax::create([
        'company_id' => $this->company->id,
        'name' => 'Exento',
        'code' => 'EX',
        'type' => TaxType::EXEMPT,
        'rate' => 0,
    ]);

    $response = $this->withHeaders(taxHeaders(true))
        ->getJson('/api/v1/taxes');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

// ============================================
// POST /api/v1/taxes (store)
// ============================================

test('POST /api/v1/taxes crea impuesto porcentual', function () {
    $response = $this->withHeaders(taxHeaders(true))
        ->postJson('/api/v1/taxes', [
            'name' => 'IVA Reducido 10%',
            'code' => 'IVA-RED',
            'type' => 'percent',
            'rate' => 10.0,
            'description' => 'Tasa reducida para alimentos básicos',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'IVA Reducido 10%')
        ->assertJsonPath('data.type', 'percent')
        ->assertJsonPath('data.rate', 10);
});

test('POST /api/v1/taxes crea impuesto fijo', function () {
    $response = $this->withHeaders(taxHeaders(true))
        ->postJson('/api/v1/taxes', [
            'name' => 'Impuesto Fijo $500',
            'code' => 'FIJO-500',
            'type' => 'fixed',
            'rate' => 500.0,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'fixed')
        ->assertJsonPath('data.rate', 500);
});

test('POST /api/v1/taxes crea impuesto exento', function () {
    $response = $this->withHeaders(taxHeaders(true))
        ->postJson('/api/v1/taxes', [
            'name' => 'Exento',
            'code' => 'EXENTO',
            'type' => 'exempt',
            'rate' => 0,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'exempt')
        ->assertJsonPath('data.is_exempt', true);
});

test('POST /api/v1/taxes valida campos requeridos', function () {
    $response = $this->withHeaders(taxHeaders(true))
        ->postJson('/api/v1/taxes', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'code', 'type', 'rate']);
});

test('POST /api/v1/taxes valida código único por empresa', function () {
    $response = $this->withHeaders(taxHeaders(true))
        ->postJson('/api/v1/taxes', [
            'name' => 'IVA Duplicado',
            'code' => 'IVA', // Ya existe
            'type' => 'percent',
            'rate' => 19,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

test('POST /api/v1/taxes valida tipo válido', function () {
    $response = $this->withHeaders(taxHeaders(true))
        ->postJson('/api/v1/taxes', [
            'name' => 'Inválido',
            'code' => 'INV',
            'type' => 'invalid_type',
            'rate' => 10,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('type');
});

test('POST /api/v1/taxes valida tasa no negativa', function () {
    $response = $this->withHeaders(taxHeaders(true))
        ->postJson('/api/v1/taxes', [
            'name' => 'Negativo',
            'code' => 'NEG',
            'type' => 'percent',
            'rate' => -5,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('rate');
});

// ============================================
// PATCH /api/v1/taxes/{uuid} (update)
// ============================================

test('PATCH /api/v1/taxes/{uuid} actualiza impuesto', function () {
    $response = $this->withHeaders(taxHeaders(true))
        ->patchJson("/api/v1/taxes/{$this->iva19->uuid}", [
            'name' => 'IVA Actualizado 21%',
            'rate' => 21.0,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'IVA Actualizado 21%')
        ->assertJsonPath('data.rate', 21);
});

// ============================================
// POST /api/v1/taxes/{uuid}/mark-default
// ============================================

test('POST /api/v1/taxes/{uuid}/mark-default desmarca otros defaults', function () {
    $exento = Tax::create([
        'company_id' => $this->company->id,
        'name' => 'Exento',
        'code' => 'EX',
        'type' => TaxType::EXEMPT,
        'rate' => 0,
        'is_default' => false,
    ]);

    expect(Tax::find($this->iva19->id)->is_default)->toBeTrue();
    expect(Tax::find($exento->id)->is_default)->toBeFalse();

    $response = $this->withHeaders(taxHeaders(true))
        ->postJson("/api/v1/taxes/{$exento->uuid}/mark-default");

    $response->assertOk();

    $this->iva19->refresh();
    $exento->refresh();

    expect($this->iva19->is_default)->toBeFalse();
    expect($exento->is_default)->toBeTrue();
});

// ============================================
// DELETE /api/v1/taxes/{uuid}
// ============================================

test('DELETE /api/v1/taxes/{uuid} elimina impuesto sin uso', function () {
    $unused = Tax::create([
        'company_id' => $this->company->id,
        'name' => 'Sin Uso',
        'code' => 'UNUSED',
        'type' => TaxType::PERCENT,
        'rate' => 5,
    ]);

    $response = $this->withHeaders(taxHeaders(true))
        ->deleteJson("/api/v1/taxes/{$unused->uuid}");

    $response->assertOk()
        ->assertJsonPath('success', true);

    expect(Tax::where('uuid', $unused->uuid)->exists())->toBeFalse();
});

test('DELETE /api/v1/taxes/{uuid} rechaza eliminación si está en uso', function () {
    // Crear producto que usa el IVA 19%
    $category = Category::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name_translations' => ['es' => 'Categoría Test'],
        'sort_order' => 1,
        'tax_id' => $this->iva19->id,
    ]);

    Product::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Producto Test'],
        'base_price' => 1000,
        'is_active' => true,
        'tax_id' => $this->iva19->id,
    ]);

    $response = $this->withHeaders(taxHeaders(true))
        ->deleteJson("/api/v1/taxes/{$this->iva19->uuid}");

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonFragment(['message' => 'No se puede eliminar: 1 productos y 1 categorías usan este impuesto. Reasígnelos primero.']);
});
