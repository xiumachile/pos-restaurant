<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\Entities\ProductRecipe;
use Modules\Recipes\Domain\ValueObjects\DimensionType;
use Modules\Recipes\Domain\ValueObjects\BaseUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'REC-API-' . uniqid(),
        'legal_name' => 'Recipes API Company',
        'trade_name' => 'Recipes API Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'REC-API',
        'name' => 'Recipes API Branch',
        'allow_negative_stock' => false,
    ]);

    $this->manager = User::create([
        'name' => 'Test Manager',
        'email' => 'manager-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);

    $this->token = JWTAuth::fromUser($this->manager);
});

function recipesHeaders(): array
{
    return [
        'Authorization' => "Bearer " . test()->token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

function createTestCategory($test): Category
{
    return Category::create([
        'company_id' => $test->company->id,
        'name_translations' => ['es' => 'Platos Fuertes'],
        'sort_order' => 1,
    ]);
}

function createTestProduct($test): Product
{
    $category = createTestCategory($test);

    return Product::create([
        'company_id' => $test->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Carne Mongoliana', 'zh' => '蒙古牛肉'],
        'base_price' => 12000,
        'is_active' => true,
    ]);
}

// ============================================
// POST /api/v1/recipes/ingredients - Crear Insumo
// ============================================

test('POST /api/v1/recipes/ingredients crea insumo base', function () {
    $response = $this->withHeaders(recipesHeaders())
        ->postJson('/api/v1/recipes/ingredients', [
            'sku' => 'LOMO-001',
            'name_translations' => [
                'es' => 'Lomo de Vacuno',
                'zh' => '牛柳肉',
            ],
            'dimension_type' => 'mass',
            'base_unit' => 'g',
            'minimum_stock_base' => 5000,
            'initial_cost_per_base_unit' => 10,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.sku', 'LOMO-001')
        ->assertJsonPath('data.dimension_type', 'mass')
        ->assertJsonPath('data.base_unit', 'g')
        ->assertJsonPath('data.current_stock_base', 0)
        ->assertJsonPath('data.is_low_stock', true);
});

test('POST /api/v1/recipes/ingredients requiere campos obligatorios', function () {
    $response = $this->withHeaders(recipesHeaders())
        ->postJson('/api/v1/recipes/ingredients', [
            'sku' => 'TEST',
        ]);

    $response->assertStatus(422);
});

// ============================================
// POST /api/v1/recipes/ingredients/{uuid}/purchase - Registrar Compra
// ============================================

test('POST /api/v1/recipes/ingredients/{uuid}/purchase registra compra con conversión', function () {
    // Crear insumo primero
    $ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'ARROZ-001',
        'name_translations' => ['es' => 'Arroz'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 0,
        'cost_per_base_unit' => 0,
    ]);

    // Comprar 1 saco de 25 Kg a $150.000 CLP
    $response = $this->withHeaders(recipesHeaders())
        ->postJson("/api/v1/recipes/ingredients/{$ingredient->uuid}/purchase", [
            'purchase_unit_name' => 'Saco 25Kg',
            'purchase_quantity' => 1,
            'total_purchase_cost' => 150000,
            'conversion_factor_to_base' => 25000, // 25 Kg = 25000 g
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.total_base_quantity_added', 25000)
        ->assertJsonPath('data.new_stock_base', 25000);

    // Verificar que el costo por gramo es correcto: $150.000 / 25000g = $6/g
    $ingredient->refresh();
    expect((float) $ingredient->cost_per_base_unit)->toBe(6.0);
});

test('POST /api/v1/recipes/ingredients/{uuid}/purchase calcula costo promedio ponderado', function () {
    // Crear insumo con stock previo
    $ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'HARINA-001',
        'name_translations' => ['es' => 'Harina'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 10000, // 10 Kg previos
        'cost_per_base_unit' => 2.0,   // $2/g previo
    ]);

    // Comprar 5 Kg más a $3/g
    $response = $this->withHeaders(recipesHeaders())
        ->postJson("/api/v1/recipes/ingredients/{$ingredient->uuid}/purchase", [
            'purchase_unit_name' => 'Bolsa 5Kg',
            'purchase_quantity' => 1,
            'total_purchase_cost' => 15000, // $15.000 por 5 Kg
            'conversion_factor_to_base' => 5000,
        ]);

    $response->assertStatus(201);

    $ingredient->refresh();
    // Costo promedio ponderado: (10000*2 + 15000) / 15000 = 35000/15000 = 2.333...
    expect((float) $ingredient->current_stock_base)->toBe(15000.0);
    expect(round((float) $ingredient->cost_per_base_unit, 2))->toBe(2.33);
});

// ============================================
// POST /api/v1/recipes - Crear Ficha Técnica
// ============================================

test('POST /api/v1/recipes crea ficha técnica con ingredientes', function () {
    $product = createTestProduct($this);

    // Crear insumos
    $lomo = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'LOMO',
        'name_translations' => ['es' => 'Lomo Vacuno'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 20000,
        'cost_per_base_unit' => 10.0,
    ]);

    $arroz = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'ARROZ',
        'name_translations' => ['es' => 'Arroz'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 50000,
        'cost_per_base_unit' => 2.0,
    ]);

    $response = $this->withHeaders(recipesHeaders())
        ->postJson('/api/v1/recipes', [
            'product_uuid' => $product->uuid,
            'description' => 'Receta de Carne Mongoliana al Wok',
            'yield_servings' => 1,
            'ingredients' => [
                [
                    'raw_ingredient_id' => $lomo->id,
                    'quantity_base_unit' => 180,
                    'waste_percentage' => 10,
                ],
                [
                    'raw_ingredient_id' => $arroz->id,
                    'quantity_base_unit' => 150,
                    'waste_percentage' => 5,
                ],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.product_name', 'Carne Mongoliana')
        ->assertJsonCount(2, 'data.items');

    // Verificar Food Cost
    // Lomo: 180g * 1.10 = 198g * $10 = $1.980
    // Arroz: 150g * 1.05 = 157.5g * $2 = $315
    // Total: $2.295
    $recipeCost = $response->json('data.total_recipe_cost');
    expect(round($recipeCost, 2))->toBe(2295.0);

    // Food Cost %: (2295 / 12000) * 100 = 19.125%
    expect($response->json('data.food_cost_percentage'))->toBe(19.13);
});

test('POST /api/v1/recipes deniega si producto ya tiene receta', function () {
    $product = createTestProduct($this);

    $ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'LOMO',
        'name_translations' => ['es' => 'Lomo'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 20000,
        'cost_per_base_unit' => 10.0,
    ]);

    // Crear primera receta
    $this->withHeaders(recipesHeaders())
        ->postJson('/api/v1/recipes', [
            'product_uuid' => $product->uuid,
            'ingredients' => [
                [
                    'raw_ingredient_id' => $ingredient->id,
                    'quantity_base_unit' => 180,
                ],
            ],
        ])
        ->assertStatus(201);

    // Intentar crear segunda receta para el mismo producto
    $response = $this->withHeaders(recipesHeaders())
        ->postJson('/api/v1/recipes', [
            'product_uuid' => $product->uuid,
            'ingredients' => [
                [
                    'raw_ingredient_id' => $ingredient->id,
                    'quantity_base_unit' => 200,
                ],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'recipe_exists');
});

// ============================================
// GET /api/v1/recipes/product/{uuid} - Obtener Receta
// ============================================

test('GET /api/v1/recipes/product/{uuid} retorna receta con ingredientes', function () {
    $product = createTestProduct($this);

    $ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'LOMO',
        'name_translations' => ['es' => 'Lomo'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 20000,
        'cost_per_base_unit' => 10.0,
    ]);

    ProductRecipe::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'yield_servings' => 1,
        'total_recipe_cost' => 1980,
    ]);

    $recipe = ProductRecipe::where('product_id', $product->id)->first();

    \Modules\Recipes\Domain\Entities\RecipeItem::createWithCalculation(
        recipeId: $recipe->id,
        ingredient: $ingredient,
        quantityBase: 180.0,
        wastePercentage: 10.0
    );

    $response = $this->withHeaders(recipesHeaders())
        ->getJson("/api/v1/recipes/product/{$product->uuid}");

    $response->assertOk()
        ->assertJsonPath('data.product_name', 'Carne Mongoliana')
        ->assertJsonCount(1, 'data.items');
});

test('GET /api/v1/recipes/product/{uuid} retorna 404 si no hay receta', function () {
    $product = createTestProduct($this);

    $response = $this->withHeaders(recipesHeaders())
        ->getJson("/api/v1/recipes/product/{$product->uuid}");

    $response->assertStatus(404);
});

// ============================================
// GET /api/v1/recipes/food-cost - Reporte Food Cost
// ============================================

test('GET /api/v1/recipes/food-cost retorna reporte', function () {
    $product = createTestProduct($this);

    $ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'LOMO',
        'name_translations' => ['es' => 'Lomo'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 20000,
        'cost_per_base_unit' => 10.0,
    ]);

    $recipe = ProductRecipe::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'yield_servings' => 1,
        'total_recipe_cost' => 0,
    ]);

    \Modules\Recipes\Domain\Entities\RecipeItem::createWithCalculation(
        recipeId: $recipe->id,
        ingredient: $ingredient,
        quantityBase: 180.0,
        wastePercentage: 10.0
    );

    $response = $this->withHeaders(recipesHeaders())
        ->getJson('/api/v1/recipes/food-cost');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product_name', 'Carne Mongoliana')
        ->assertJsonPath('data.0.recipe_cost', 1980);
});

// ============================================
// Autorización
// ============================================

test('sin autenticacion retorna 401', function () {
    $response = $this->getJson('/api/v1/recipes/ingredients');
    $response->assertStatus(401);
});
