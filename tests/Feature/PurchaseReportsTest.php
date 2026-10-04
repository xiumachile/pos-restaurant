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
use Carbon\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'PR-' . uniqid(),
        'legal_name' => 'Purchase Reports Test',
        'trade_name' => 'PR Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'PR',
        'name' => 'PR Branch',
    ]);

    $this->manager = User::create([
        'name' => 'Manager PR',
        'email' => 'pr-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);

    $this->token = JWTAuth::fromUser($this->manager);

    // Crear insumos de prueba
    $this->ingredient1 = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'CARNE-PR',
        'name_translations' => ['es' => 'Carne PR'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 0,
        'cost_per_base_unit' => 10,
    ]);

    $this->ingredient2 = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'ACEITE-PR',
        'name_translations' => ['es' => 'Aceite PR'],
        'dimension_type' => DimensionType::VOLUME,
        'base_unit' => BaseUnit::MILLILITER,
        'current_stock_base' => 0,
        'cost_per_base_unit' => 5,
    ]);

    // Crear compras en diferentes fechas y con diferentes documentos
    $this->purchase1 = RawIngredientPurchase::create([
        'raw_ingredient_id' => $this->ingredient1->id,
        'user_id' => $this->manager->id,
        'purchase_unit_name' => 'kg',
        'purchase_quantity' => 10,
        'conversion_factor_to_base' => 1000,
        'total_base_quantity_added' => 10000,
        'total_purchase_cost' => 50000,
        'calculated_cost_per_base_unit' => 5,
        'purchase_date' => Carbon::now()->subDays(3),
        'document_type' => 'factura',
        'document_number' => 'F-1234',
        'supplier_name' => 'Carnes Premium SpA',
        'supplier_rut' => '77.888.999-K',
    ]);

    $this->purchase2 = RawIngredientPurchase::create([
        'raw_ingredient_id' => $this->ingredient2->id,
        'user_id' => $this->manager->id,
        'purchase_unit_name' => 'l',
        'purchase_quantity' => 5,
        'conversion_factor_to_base' => 1000,
        'total_base_quantity_added' => 5000,
        'total_purchase_cost' => 25000,
        'calculated_cost_per_base_unit' => 5,
        'purchase_date' => Carbon::now()->subDays(5),
        'document_type' => 'boleta',
        'document_number' => 'B-5678',
        'supplier_name' => 'Mercado Local',
    ]);

    $this->purchase3 = RawIngredientPurchase::create([
        'raw_ingredient_id' => $this->ingredient1->id,
        'user_id' => $this->manager->id,
        'purchase_unit_name' => 'kg',
        'purchase_quantity' => 20,
        'conversion_factor_to_base' => 1000,
        'total_base_quantity_added' => 20000,
        'total_purchase_cost' => 90000,
        'calculated_cost_per_base_unit' => 4.5,
        'purchase_date' => Carbon::now()->subDays(15),
        'document_type' => 'factura',
        'document_number' => 'F-9999',
        'supplier_name' => 'Carnes Premium SpA',
        'supplier_rut' => '77.888.999-K',
    ]);
});

test('GET /reports/purchases/kpis retorna métricas correctas', function () {
    $from = Carbon::now()->subDays(30)->format('Y-m-d');
    $to = Carbon::now()->format('Y-m-d');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson("/api/v1/reports/purchases/kpis?from_date={$from}&to_date={$to}");

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => [
            'total_amount',
            'purchases_count',
            'average_ticket',
            'with_document_count',
        ]]);

    $data = $response->json('data');
    expect($data['purchases_count'])->toBe(3);
    expect($data['total_amount'])->toBe(165000); // 50000 + 25000 + 90000
    expect($data['average_ticket'])->toBe(55000);
    expect($data['with_document_count'])->toBe(3); // todas con documento
});

test('GET /reports/purchases/by-document-type agrupa correctamente', function () {
    $from = Carbon::now()->subDays(30)->format('Y-m-d');
    $to = Carbon::now()->format('Y-m-d');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson("/api/v1/reports/purchases/by-document-type?from_date={$from}&to_date={$to}");

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['*' => ['document_type', 'count', 'total_amount']]]);

    $data = $response->json('data');
    expect($data)->toHaveCount(2); // factura y boleta

    $factura = collect($data)->firstWhere('document_type', 'factura');
    $boleta = collect($data)->firstWhere('document_type', 'boleta');

    expect($factura['count'])->toBe(2);
    expect($factura['total_amount'])->toBe(140000); // 50000 + 90000
    expect($boleta['count'])->toBe(1);
    expect($boleta['total_amount'])->toBe(25000);
});

test('GET /reports/purchases/top-suppliers retorna ranking', function () {
    $from = Carbon::now()->subDays(30)->format('Y-m-d');
    $to = Carbon::now()->format('Y-m-d');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson("/api/v1/reports/purchases/top-suppliers?from_date={$from}&to_date={$to}&limit=10");

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['*' => ['supplier_name', 'purchases_count', 'total_amount']]]);

    $data = $response->json('data');
    expect($data)->toHaveCount(2);

    // Carnes Premium debe estar primero (más monto)
    expect($data[0]['supplier_name'])->toBe('Carnes Premium SpA');
    expect($data[0]['purchases_count'])->toBe(2);
    expect($data[0]['total_amount'])->toBe(140000);
});

test('GET /reports/purchases/top-ingredients retorna insumos más comprados', function () {
    $from = Carbon::now()->subDays(30)->format('Y-m-d');
    $to = Carbon::now()->format('Y-m-d');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson("/api/v1/reports/purchases/top-ingredients?from_date={$from}&to_date={$to}&limit=10");

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['*' => ['ingredient_name', 'ingredient_sku', 'purchases_count', 'total_quantity', 'total_amount']]]);

    $data = $response->json('data');
    expect($data)->toHaveCount(2);

    // Carne PR tiene 2 compras (10kg + 20kg = 30kg = 30000g)
    $carne = collect($data)->firstWhere('ingredient_sku', 'CARNE-PR');
    expect($carne)->not->toBeNull();
    expect($carne['purchases_count'])->toBe(2);
    expect($carne['total_quantity'])->toBe(30000);
    expect($carne['total_amount'])->toBe(140000);
});

test('GET /reports/purchases/kpis respeta filtro de fechas', function () {
    // Solo últimos 7 días (debe excluir la compra de hace 15 días)
    $from = Carbon::now()->subDays(7)->format('Y-m-d');
    $to = Carbon::now()->format('Y-m-d');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson("/api/v1/reports/purchases/kpis?from_date={$from}&to_date={$to}");

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data['purchases_count'])->toBe(2); // Solo las de hace 3 y 5 días
    expect($data['total_amount'])->toBe(75000); // 50000 + 25000
});

test('GET /reports/purchases/kpis valida rango máximo de 365 días', function () {
    $from = Carbon::now()->subDays(400)->format('Y-m-d');
    $to = Carbon::now()->format('Y-m-d');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson("/api/v1/reports/purchases/kpis?from_date={$from}&to_date={$to}");

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['from_date']);
});
