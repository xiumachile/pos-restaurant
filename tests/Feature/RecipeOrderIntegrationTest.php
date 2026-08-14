<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\Entities\ProductRecipe;
use Modules\Recipes\Domain\Entities\RecipeItem;
use Modules\Recipes\Domain\ValueObjects\DimensionType;
use Modules\Recipes\Domain\ValueObjects\BaseUnit;
use Modules\Recipes\Domain\Listeners\DeductRecipeOnOrderConfirm;
use Modules\Recipes\Domain\Services\RecipeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'REC-INT-' . uniqid(),
        'legal_name' => 'Recipe Integration Test',
        'trade_name' => 'Integration Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'INT',
        'name' => 'Integration Branch',
        'allow_negative_stock' => false,
    ]);

    $this->waiter = User::create([
        'name' => 'Test Waiter',
        'email' => 'waiter-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->token = JWTAuth::fromUser($this->waiter);
});

// ============================================
// Test 1: Stock se descuenta al confirmar pedido
// ============================================
test('al confirmar pedido se descuentan insumos de receta', function () {
    // Crear producto
    $category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Platos'],
        'sort_order' => 1,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Carne Mongoliana'],
        'base_price' => 12000,
        'is_active' => true,
    ]);

    // Crear insumos con stock
    $lomo = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'LOMO',
        'name_translations' => ['es' => 'Lomo Vacuno'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 20000, // 20 Kg
        'cost_per_base_unit' => 10.0,
    ]);

    $arroz = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'ARROZ',
        'name_translations' => ['es' => 'Arroz'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 50000, // 50 Kg
        'cost_per_base_unit' => 2.0,
    ]);

    // Crear receta: 180g lomo + 10% merma = 198g, 150g arroz + 5% merma = 157.5g
    $recipe = ProductRecipe::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'yield_servings' => 1,
        'total_recipe_cost' => 0,
    ]);

    RecipeItem::createWithCalculation($recipe->id, $lomo, 180.0, 10.0); // 198g efectivos
    RecipeItem::createWithCalculation($recipe->id, $arroz, 150.0, 5.0); // 157.5g efectivos

    // Crear pedido con 2 platos
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 24000,
        'tax_amount' => 4560,
        'discount_amount' => 0,
        'total' => 28560,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $product->id,
        'name_snapshot' => 'Carne Mongoliana',
        'quantity' => 2, // 2 platos
        'unit_price_snapshot' => 12000,
        'subtotal' => 24000,
    ]);

    // Stock antes: Lomo 20000g, Arroz 50000g
    expect((float) $lomo->current_stock_base)->toBe(20000.0);
    expect((float) $arroz->current_stock_base)->toBe(50000.0);

    // Disparar evento OrderConfirmed
    event(new OrderConfirmed($order));

    // Recargar insumos
    $lomo->refresh();
    $arroz->refresh();

    // Stock después:
    // Lomo: 20000 - (198g * 2 platos) = 20000 - 396 = 19604g
    // Arroz: 50000 - (157.5g * 2 platos) = 50000 - 315 = 49685g
    expect((float) $lomo->current_stock_base)->toBe(19604.0);
    expect((float) $arroz->current_stock_base)->toBe(49685.0);
});

// ============================================
// Test 2: Producto sin receta no afecta stock
// ============================================
test('producto sin receta no descuenta insumos', function () {
    // Crear producto SIN receta
    $category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Bebidas'],
        'sort_order' => 1,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Coca Cola'],
        'base_price' => 2500,
        'is_active' => true,
    ]);

    // Crear insumo (no relacionado con el producto)
    $ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'AZUCAR',
        'name_translations' => ['es' => 'Azúcar'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 10000,
        'cost_per_base_unit' => 1.0,
    ]);

    // Crear pedido
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 5000,
        'tax_amount' => 950,
        'discount_amount' => 0,
        'total' => 5950,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $product->id,
        'name_snapshot' => 'Coca Cola',
        'quantity' => 2,
        'unit_price_snapshot' => 2500,
        'subtotal' => 5000,
    ]);

    // Stock antes: 10000g
    expect((float) $ingredient->current_stock_base)->toBe(10000.0);

    // Disparar evento
    event(new OrderConfirmed($order));

    // Stock después: sigue igual (10000g) porque el producto no tiene receta
    $ingredient->refresh();
    expect((float) $ingredient->current_stock_base)->toBe(10000.0);
});

// ============================================
// Test 3: Múltiples productos con receta
// ============================================
test('pedido con múltiples productos descuenta correctamente', function () {
    $category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Platos'],
        'sort_order' => 1,
    ]);

    // Producto 1: Carne Mongoliana
    $product1 = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Carne Mongoliana'],
        'base_price' => 12000,
        'is_active' => true,
    ]);

    // Producto 2: Arroz Chaufa
    $product2 = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Arroz Chaufa'],
        'base_price' => 8000,
        'is_active' => true,
    ]);

    // Insumo compartido: Arroz
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

    // Receta Producto 1: 150g arroz
    $recipe1 = ProductRecipe::create([
        'company_id' => $this->company->id,
        'product_id' => $product1->id,
        'yield_servings' => 1,
        'total_recipe_cost' => 0,
    ]);
    RecipeItem::createWithCalculation($recipe1->id, $arroz, 150.0, 5.0); // 157.5g efectivos

    // Receta Producto 2: 200g arroz
    $recipe2 = ProductRecipe::create([
        'company_id' => $this->company->id,
        'product_id' => $product2->id,
        'yield_servings' => 1,
        'total_recipe_cost' => 0,
    ]);
    RecipeItem::createWithCalculation($recipe2->id, $arroz, 200.0, 5.0); // 210g efectivos

    // Crear pedido: 1x Carne Mongoliana + 2x Arroz Chaufa
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 28000,
        'tax_amount' => 5320,
        'discount_amount' => 0,
        'total' => 33320,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $product1->id,
        'name_snapshot' => 'Carne Mongoliana',
        'quantity' => 1,
        'unit_price_snapshot' => 12000,
        'subtotal' => 12000,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $product2->id,
        'name_snapshot' => 'Arroz Chaufa',
        'quantity' => 2,
        'unit_price_snapshot' => 8000,
        'subtotal' => 16000,
    ]);

    // Stock antes: 50000g
    expect((float) $arroz->current_stock_base)->toBe(50000.0);

    // Disparar evento
    event(new OrderConfirmed($order));

    // Stock después:
    // 50000 - (157.5g * 1) - (210g * 2) = 50000 - 157.5 - 420 = 49422.5g
    $arroz->refresh();
    expect((float) $arroz->current_stock_base)->toBe(49422.5);
});
