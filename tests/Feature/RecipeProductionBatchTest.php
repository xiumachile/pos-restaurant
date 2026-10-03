<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\Entities\ProductRecipe;
use Modules\Recipes\Domain\Entities\RecipeItem;
use Modules\Recipes\Domain\Entities\RawIngredientMovement;
use Modules\Recipes\Domain\ValueObjects\DimensionType;
use Modules\Recipes\Domain\ValueObjects\BaseUnit;
use Modules\Recipes\Domain\ValueObjects\MovementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'PROD-' . uniqid(),
        'legal_name' => 'Production Test Company',
        'trade_name' => 'Production Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'PROD',
        'name' => 'Production Branch',
        'allow_negative_stock' => false,
    ]);

    $this->manager = User::create([
        'name' => 'Test Manager',
        'email' => 'prod-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);

    $this->token = JWTAuth::fromUser($this->manager);
});

function prodHeaders(): array
{
    return [
        'Authorization' => 'Bearer ' . test()->token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

function createProductWithRecipe($test, array $recipeItems, float $initialStock = 20000): array
{
    $category = Category::create([
        'company_id' => $test->company->id,
        'name_translations' => ['es' => 'Platos'],
        'sort_order' => 1,
    ]);

    $product = Product::create([
        'company_id' => $test->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Producto de prueba'],
        'base_price' => 12000,
        'is_active' => true,
    ]);

    $ingredients = [];
    foreach ($recipeItems as $idx => $item) {
        $ingredients[] = RawIngredient::create([
            'company_id' => $test->company->id,
            'branch_id' => $test->branch->id,
            'sku' => 'ING-' . $idx . '-' . uniqid(),
            'name_translations' => ['es' => 'Ingrediente ' . $idx],
            'dimension_type' => DimensionType::MASS,
            'base_unit' => BaseUnit::GRAM,
            'current_stock_base' => $initialStock,
            'cost_per_base_unit' => 5.0,
        ]);
    }

    $recipe = ProductRecipe::create([
        'company_id' => $test->company->id,
        'product_id' => $product->id,
        'yield_servings' => 1,
        'total_recipe_cost' => 0,
    ]);

    foreach ($recipeItems as $idx => $item) {
        RecipeItem::createWithCalculation(
            $recipe->id,
            $ingredients[$idx],
            $item['quantity'],
            $item['waste'] ?? 0
        );
    }

    return [$product, $recipe, $ingredients];
}

// ============================================
// Happy path
// ============================================

test('POST /production-batches crea lote y descuenta ingredientes', function () {
    [$product, $recipe, $ingredients] = createProductWithRecipe($this, [
        ['quantity' => 180.0, 'waste' => 10.0], // 198g efectivos
    ]);

    $response = $this->withHeaders(prodHeaders())
        ->postJson('/api/v1/recipes/production-batches', [
            'product_uuid' => $product->uuid,
            'quantity' => 2,
        ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'data' => [
            'uuid',
            'product_uuid',
            'quantity',
            'movements_count',
            'created_at',
        ],
    ]);

    // Debe crear 1 movimiento OutConsumption por ingrediente
    expect(RawIngredientMovement::count())->toBe(1);

    $movement = RawIngredientMovement::first();
    expect($movement->type)->toBe(MovementType::OutConsumption);
    expect($movement->reference_type)->toBe('production_batch');
    expect((float) $movement->quantity_base)->toBe(396.0); // 198g * 2 lotes

    // Stock descontado
    $ingredients[0]->refresh();
    expect((float) $ingredients[0]->current_stock_base)->toBe(19604.0); // 20000 - 396
});

test('POST /production-batches con múltiples ingredientes descuenta todos', function () {
    [$product, $recipe, $ingredients] = createProductWithRecipe($this, [
        ['quantity' => 100.0, 'waste' => 0.0],
        ['quantity' => 50.0, 'waste' => 5.0], // 52.5g efectivos
    ]);

    $response = $this->withHeaders(prodHeaders())
        ->postJson('/api/v1/recipes/production-batches', [
            'product_uuid' => $product->uuid,
            'quantity' => 3,
        ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.movements_count', 2);

    expect(RawIngredientMovement::count())->toBe(2);

    // Verificar que ambos ingredientes fueron descontados
    $ingredients[0]->refresh();
    $ingredients[1]->refresh();

    // 100g * 3 = 300g
    expect((float) $ingredients[0]->current_stock_base)->toBe(19700.0);
    // 52.5g * 3 = 157.5g
    expect((float) $ingredients[1]->current_stock_base)->toBe(19842.5);
});

test('POST /production-batches acepta batch_notes opcional', function () {
    [$product, $recipe, $ingredients] = createProductWithRecipe($this, [
        ['quantity' => 100.0, 'waste' => 0.0],
    ]);

    $response = $this->withHeaders(prodHeaders())
        ->postJson('/api/v1/recipes/production-batches', [
            'product_uuid' => $product->uuid,
            'quantity' => 1,
            'batch_notes' => 'Lote de prueba matutino',
        ]);

    $response->assertStatus(201);

    $movement = RawIngredientMovement::first();
    expect($movement->reason)->toBe('Lote de prueba matutino');
});

// ============================================
// Validaciones
// ============================================

test('POST /production-batches falla con 422 si product_uuid no existe', function () {
    $response = $this->withHeaders(prodHeaders())
        ->postJson('/api/v1/recipes/production-batches', [
            'product_uuid' => '00000000-0000-0000-0000-000000000000',
            'quantity' => 1,
        ]);

    $response->assertStatus(404);
});

test('POST /production-batches falla con 422 si quantity <= 0', function () {
    [$product, $recipe, $ingredients] = createProductWithRecipe($this, [
        ['quantity' => 100.0, 'waste' => 0.0],
    ]);

    $response = $this->withHeaders(prodHeaders())
        ->postJson('/api/v1/recipes/production-batches', [
            'product_uuid' => $product->uuid,
            'quantity' => 0,
        ]);

    $response->assertStatus(422);
});

test('POST /production-batches falla con 422 si quantity no es entero', function () {
    [$product, $recipe, $ingredients] = createProductWithRecipe($this, [
        ['quantity' => 100.0, 'waste' => 0.0],
    ]);

    $response = $this->withHeaders(prodHeaders())
        ->postJson('/api/v1/recipes/production-batches', [
            'product_uuid' => $product->uuid,
            'quantity' => 1.5,
        ]);

    $response->assertStatus(422);
});

test('POST /production-batches falla con 404 si producto no tiene receta', function () {
    $category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Platos'],
        'sort_order' => 1,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Producto sin receta'],
        'base_price' => 5000,
        'is_active' => true,
    ]);

    $response = $this->withHeaders(prodHeaders())
        ->postJson('/api/v1/recipes/production-batches', [
            'product_uuid' => $product->uuid,
            'quantity' => 1,
        ]);

    $response->assertStatus(404);
    $response->assertJsonPath('error', 'recipe_not_found');
});

test('POST /production-batches falla con 409 si stock insuficiente', function () {
    [$product, $recipe, $ingredients] = createProductWithRecipe($this, [
        ['quantity' => 100.0, 'waste' => 0.0],
    ], initialStock: 50); // Stock insuficiente

    $response = $this->withHeaders(prodHeaders())
        ->postJson('/api/v1/recipes/production-batches', [
            'product_uuid' => $product->uuid,
            'quantity' => 1,
        ]);

    $response->assertStatus(409);
    $response->assertJsonPath('error', 'insufficient_stock');

    // No se crean movimientos (transaccionalidad)
    expect(RawIngredientMovement::count())->toBe(0);

    // Stock no cambió
    $ingredients[0]->refresh();
    expect((float) $ingredients[0]->current_stock_base)->toBe(50.0);
});

test('POST /production-batches requiere autenticación', function () {
    $response = $this->postJson('/api/v1/recipes/production-batches', [
        'product_uuid' => '00000000-0000-0000-0000-000000000000',
        'quantity' => 1,
    ]);

    $response->assertStatus(401);
});
