<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\Entities\RawIngredientPurchase;
use Modules\Recipes\Domain\Entities\ProductRecipe;
use Modules\Recipes\Domain\Entities\RecipeItem;
use Modules\Recipes\Domain\ValueObjects\DimensionType;
use Modules\Recipes\Domain\ValueObjects\BaseUnit;
use Modules\Recipes\Domain\Exceptions\InsufficientIngredientStockException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'REC-' . uniqid(),
        'legal_name' => 'Recipes Test Company',
        'trade_name' => 'Recipes Test Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'REC',
        'name' => 'Recipes Branch',
        'allow_negative_stock' => false,
    ]);

    $this->chef = User::create([
        'name' => 'Test Chef',
        'email' => 'chef-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);
});

// ============================================
// Value Objects
// ============================================

test('DimensionType tiene unidades base SI correctas', function () {
    expect(DimensionType::MASS->baseUnit())->toBe(BaseUnit::GRAM);
    expect(DimensionType::VOLUME->baseUnit())->toBe(BaseUnit::MILLILITER);
    expect(DimensionType::COUNT->baseUnit())->toBe(BaseUnit::UNIT);
});

test('BaseUnit convierte correctamente a unidad base SI', function () {
    // 1 kg = 1000 g
    expect(BaseUnit::KILOGRAM->toBase(1.0))->toBe(1000.0);
    expect(BaseUnit::KILOGRAM->toBase(2.5))->toBe(2500.0);

    // 1 L = 1000 ml
    expect(BaseUnit::LITER->toBase(1.0))->toBe(1000.0);

    // 1 lb = 453.592 g
    expect(BaseUnit::POUND->toBase(1.0))->toBe(453.592);

    // 1 doc = 12 un
    expect(BaseUnit::DOZEN->toBase(1.0))->toBe(12.0);

    // 1 g = 1 g (base)
    expect(BaseUnit::GRAM->toBase(100.0))->toBe(100.0);
});

test('BaseUnit reporta su dimension correctamente', function () {
    expect(BaseUnit::GRAM->dimension())->toBe(DimensionType::MASS);
    expect(BaseUnit::KILOGRAM->dimension())->toBe(DimensionType::MASS);
    expect(BaseUnit::MILLILITER->dimension())->toBe(DimensionType::VOLUME);
    expect(BaseUnit::LITER->dimension())->toBe(DimensionType::VOLUME);
    expect(BaseUnit::UNIT->dimension())->toBe(DimensionType::COUNT);
});

// ============================================
// RawIngredient
// ============================================

test('se puede crear un insumo base', function () {
    $ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'LOMO-001',
        'name_translations' => ['es' => 'Lomo de Vacuno', 'zh' => '牛柳肉'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 0,
        'cost_per_base_unit' => 0,
    ]);

    expect($ingredient->id)->not->toBeNull();
    expect($ingredient->dimension_type)->toBe(DimensionType::MASS);
    expect($ingredient->base_unit)->toBe(BaseUnit::GRAM);
});

test('registerPurchase actualiza stock y costo promedio ponderado', function () {
    $ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'LOMO-001',
        'name_translations' => ['es' => 'Lomo'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 0,
        'cost_per_base_unit' => 0,
    ]);

    // Compra 1 saco de 20 Kg a $200.000 CLP
    $purchase = $ingredient->registerPurchase(
        purchaseUnitName: 'Saco 20Kg',
        purchaseQuantity: 1,
        conversionFactorToBase: 20000, // 20 Kg = 20000 g
        totalPurchaseCost: 200000,
        userId: $this->chef->id
    );

    expect($purchase)->toBeInstanceOf(RawIngredientPurchase::class);
    expect((float) $purchase->total_base_quantity_added)->toBe(20000.0);

    // Verificar stock actualizado
    $ingredient->refresh();
    expect((float) $ingredient->current_stock_base)->toBe(20000.0);
    // Costo: $200.000 / 20000g = $10/g
    expect((float) $ingredient->cost_per_base_unit)->toBe(10.0);
});

test('registerPurchase calcula costo promedio ponderado con stock previo', function () {
    $ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'ARROZ-001',
        'name_translations' => ['es' => 'Arroz'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 10000, // 10 Kg previos
        'cost_per_base_unit' => 5.0,   // $5/g previo
    ]);

    // Compra nueva a $15/g (15000g por $225.000)
    $ingredient->registerPurchase(
        purchaseUnitName: 'Saco 15Kg',
        purchaseQuantity: 1,
        conversionFactorToBase: 15000,
        totalPurchaseCost: 225000,
        userId: $this->chef->id
    );

    $ingredient->refresh();
    // Stock total: 10000 + 15000 = 25000g
    expect((float) $ingredient->current_stock_base)->toBe(25000.0);

    // Costo promedio ponderado: (10000*5 + 225000) / 25000 = (50000 + 225000) / 25000 = 11
    expect((float) $ingredient->cost_per_base_unit)->toBe(11.0);
});

test('totalStockValue calcula correctamente', function () {
    $ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'ACEITE-001',
        'name_translations' => ['es' => 'Aceite'],
        'dimension_type' => DimensionType::VOLUME,
        'base_unit' => BaseUnit::MILLILITER,
        'current_stock_base' => 5000, // 5 litros
        'cost_per_base_unit' => 2.5,  // $2.5/ml
    ]);

    // 5000 ml * $2.5 = $12.500
    expect($ingredient->totalStockValue())->toBe(12500.0);
});

// ============================================
// RecipeItem (cálculo con merma)
// ============================================

test('RecipeItem calcula cantidad efectiva con merma', function () {
    // 180g + 10% merma = 198g
    expect(RecipeItem::calculateEffectiveQuantity(180, 10))->toBe(198.0);

    // 150g + 5% merma = 157.5g
    expect(RecipeItem::calculateEffectiveQuantity(150, 5))->toBe(157.5);

    // 200g sin merma = 200g
    expect(RecipeItem::calculateEffectiveQuantity(200, 0))->toBe(200.0);
});

test('RecipeItem calcula costo del ingrediente', function () {
    // 198g efectivos * $10/g = $1.980
    expect(RecipeItem::calculateItemCost(198.0, 10.0))->toBe(1980.0);
});

// ============================================
// ProductRecipe (Ficha técnica completa)
// ============================================

test('escenario completo: Carne Mongoliana con Food Cost', function () {
    // Crear producto final
    $category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Platos Fuertes'],
        'sort_order' => 1,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Carne Mongoliana al Wok', 'zh' => '蒙古牛肉'],
        'base_price' => 12000,
        'is_active' => true,
    ]);

    // Crear insumos (ya con stock y costo)
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

    $huevo = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'HUEVO',
        'name_translations' => ['es' => 'Huevo'],
        'dimension_type' => DimensionType::COUNT,
        'base_unit' => BaseUnit::UNIT,
        'current_stock_base' => 100,
        'cost_per_base_unit' => 300.0,
    ]);

    // Crear receta
    $recipe = ProductRecipe::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'description' => 'Receta de Carne Mongoliana al Wok',
        'yield_servings' => 1,
    ]);

    // Agregar ingredientes con merma
    // Lomo: 180g + 10% merma = 198g * $10 = $1.980
    RecipeItem::createWithCalculation($recipe->id, $lomo, 180.0, 10.0);

    // Arroz: 150g + 5% merma = 157.5g * $2 = $315
    RecipeItem::createWithCalculation($recipe->id, $arroz, 150.0, 5.0);

    // Huevo: 2 un sin merma = 2 * $300 = $600
    RecipeItem::createWithCalculation($recipe->id, $huevo, 2.0, 0.0);

    // Recalcular costo total
    $recipe->load('items');
    $recipe->recalculateTotalCost();

    // Costo total: $1.980 + $315 + $600 = $2.895
    expect((float) $recipe->total_recipe_cost)->toBe(2895.0);

    // Food Cost %: ($2.895 / $12.000) * 100 = 24.125%
    expect($recipe->calculateFoodCostPercentage())->toBe(24.13);

    // Margen bruto: $12.000 - $2.895 = $9.105
    expect($recipe->calculateGrossMargin())->toBe(9105.0);
});
