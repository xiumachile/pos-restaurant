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
use Modules\Recipes\Domain\Entities\RawIngredientMovement;
use Modules\Recipes\Domain\ValueObjects\DimensionType;
use Modules\Recipes\Domain\ValueObjects\BaseUnit;
use Modules\Recipes\Domain\ValueObjects\MovementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'MOV-INT-' . uniqid(),
        'legal_name' => 'Movement Integration Test',
        'trade_name' => 'Movement Integration',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'MOV-INT',
        'name' => 'Movement Integration Branch',
        'allow_negative_stock' => false,
    ]);

    $this->waiter = User::create([
        'name' => 'Test Waiter',
        'email' => 'movwaiter-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);
});

function createMovementTestSetup($test, array $recipeItems = [['quantity' => 180.0, 'waste' => 10.0]])
{
    // Producto
    $category = Category::create([
        'company_id' => $test->company->id,
        'name_translations' => ['es' => 'Platos'],
        'sort_order' => 1,
    ]);

    $product = Product::create([
        'company_id' => $test->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Carne Mongoliana'],
        'base_price' => 12000,
        'is_active' => true,
    ]);

    // Insumos
    $ingredients = [];
    foreach ($recipeItems as $idx => $item) {
        $ingredients[] = RawIngredient::create([
            'company_id' => $test->company->id,
            'branch_id' => $test->branch->id,
            'sku' => 'ING-' . $idx . '-' . uniqid(),
            'name_translations' => ['es' => 'Ingrediente ' . $idx],
            'dimension_type' => DimensionType::MASS,
            'base_unit' => BaseUnit::GRAM,
            'current_stock_base' => 20000,
            'cost_per_base_unit' => 5.0,
        ]);
    }

    // Receta
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
            $item['waste']
        );
    }

    return [$product, $ingredients];
}

// ============================================
// Test 1: Movimientos se crean al confirmar pedido
// ============================================
test('al confirmar pedido se crean movimientos OutConsumption', function () {
    [$product, $ingredients] = createMovementTestSetup($this, [
        ['quantity' => 180.0, 'waste' => 10.0], // 198g efectivos
    ]);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 12000,
        'tax_amount' => 2280,
        'discount_amount' => 0,
        'total' => 14280,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $product->id,
        'name_snapshot' => 'Carne Mongoliana',
        'quantity' => 1,
        'unit_price_snapshot' => 12000,
        'subtotal' => 12000,
    ]);

    // Antes: sin movimientos
    expect(RawIngredientMovement::count())->toBe(0);

    // Confirmar pedido
    event(new OrderConfirmed($order));

    // Después: 1 movimiento por ingrediente (1 ingrediente en la receta)
    expect(RawIngredientMovement::count())->toBe(1);

    $movement = RawIngredientMovement::first();
    expect($movement->type)->toBe(MovementType::OutConsumption);
    expect($movement->raw_ingredient_id)->toBe($ingredients[0]->id);
    expect((float) $movement->quantity_base)->toBe(198.0); // 180g + 10% merma
    expect((float) $movement->balance_after)->toBe(19802.0); // 20000 - 198
});

// ============================================
// Test 2: Movimientos tienen referencia a la orden
// ============================================
test('movimientos tienen reference_type=order y reference_id correcto', function () {
    [$product, $ingredients] = createMovementTestSetup($this, [
        ['quantity' => 100.0, 'waste' => 0.0],
    ]);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 12000,
        'tax_amount' => 2280,
        'discount_amount' => 0,
        'total' => 14280,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $product->id,
        'name_snapshot' => 'Carne Mongoliana',
        'quantity' => 1,
        'unit_price_snapshot' => 12000,
        'subtotal' => 12000,
    ]);

    event(new OrderConfirmed($order));

    $movement = RawIngredientMovement::first();
    expect($movement->reference_type)->toBe('order');
    expect($movement->reference_id)->toBe($order->id);
});

// ============================================
// Test 3: Cantidad considera quantity del OrderItem
// ============================================
test('cantidad de movimiento considera quantity del OrderItem', function () {
    [$product, $ingredients] = createMovementTestSetup($this, [
        ['quantity' => 100.0, 'waste' => 0.0], // 100g efectivos
    ]);

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
        'quantity' => 3, // 3 platos
        'unit_price_snapshot' => 12000,
        'subtotal' => 36000,
    ]);

    event(new OrderConfirmed($order));

    $movement = RawIngredientMovement::first();
    // 100g * 3 platos = 300g
    expect((float) $movement->quantity_base)->toBe(300.0);
    expect((float) $movement->balance_after)->toBe(19700.0); // 20000 - 300
});

// ============================================
// Test 4: Múltiples ingredientes generan múltiples movimientos
// ============================================
test('múltiples ingredientes generan múltiples movimientos', function () {
    [$product, $ingredients] = createMovementTestSetup($this, [
        ['quantity' => 100.0, 'waste' => 0.0],
        ['quantity' => 50.0, 'waste' => 5.0], // 52.5g efectivos
        ['quantity' => 200.0, 'waste' => 0.0],
    ]);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 12000,
        'tax_amount' => 2280,
        'discount_amount' => 0,
        'total' => 14280,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $product->id,
        'name_snapshot' => 'Carne Mongoliana',
        'quantity' => 1,
        'unit_price_snapshot' => 12000,
        'subtotal' => 12000,
    ]);

    event(new OrderConfirmed($order));

    // 3 ingredientes = 3 movimientos
    expect(RawIngredientMovement::count())->toBe(3);

    // Verificar que cada movimiento tiene el ingrediente correcto
    $ingredientIds = RawIngredientMovement::pluck('raw_ingredient_id')->sort()->values()->all();
    $expectedIds = collect($ingredients)->pluck('id')->sort()->values()->all();
    expect($ingredientIds)->toBe($expectedIds);
});

// ============================================
// Test 5: Stock insuficiente no crea movimientos (transaccionalidad)
// ============================================
test('stock insuficiente no crea movimientos (transaccionalidad)', function () {
    [$product, $ingredients] = createMovementTestSetup($this, [
        ['quantity' => 100.0, 'waste' => 0.0],
    ]);

    // Reducir stock para que sea insuficiente
    $ingredients[0]->current_stock_base = 50;
    $ingredients[0]->save();

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 12000,
        'tax_amount' => 2280,
        'discount_amount' => 0,
        'total' => 14280,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $product->id,
        'name_snapshot' => 'Carne Mongoliana',
        'quantity' => 1,
        'unit_price_snapshot' => 12000,
        'subtotal' => 12000,
    ]);

    // El listener cacha la excepción y loguea warning (best-effort)
    event(new OrderConfirmed($order));

    // No se crean movimientos porque falló
    expect(RawIngredientMovement::count())->toBe(0);

    // El stock no cambió
    $ingredients[0]->refresh();
    expect((float) $ingredients[0]->current_stock_base)->toBe(50.0);
});
