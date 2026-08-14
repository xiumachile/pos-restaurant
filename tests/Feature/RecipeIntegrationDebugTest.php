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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'DEBUG-' . uniqid(),
        'legal_name' => 'Debug Test',
        'trade_name' => 'Debug Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'DBG',
        'name' => 'Debug Branch',
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
});

test('debug: verificar que OrderItem tiene product_id', function () {
    $category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Platos'],
        'sort_order' => 1,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Test Product'],
        'base_price' => 1000,
        'is_active' => true,
    ]);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-DEBUG',
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $product->id,
        'name_snapshot' => 'Test Product',
        'quantity' => 1,
        'unit_price_snapshot' => 1000,
        'subtotal' => 1000,
    ]);

    dump('OrderItem structure:', [
        'id' => $orderItem->id,
        'order_id' => $orderItem->order_id,
        'product_id' => $orderItem->product_id,
        'company_id' => $orderItem->company_id,
    ]);

    expect($orderItem->product_id)->toBe($product->id);
});

test('debug: verificar que RecipeService descuenta correctamente', function () {
    $category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Platos'],
        'sort_order' => 1,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Test Product'],
        'base_price' => 1000,
        'is_active' => true,
    ]);

    $ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'TEST-ING',
        'name_translations' => ['es' => 'Test Ingredient'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 10000,
        'cost_per_base_unit' => 1.0,
    ]);

    $recipe = ProductRecipe::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'yield_servings' => 1,
        'total_recipe_cost' => 0,
    ]);

    RecipeItem::createWithCalculation($recipe->id, $ingredient, 100.0, 0.0);

    dump('Recipe created:', [
        'recipe_id' => $recipe->id,
        'product_id' => $recipe->product_id,
        'items_count' => $recipe->items()->count(),
    ]);

    // Buscar receta manualmente
    $foundRecipe = ProductRecipe::withoutGlobalScopes()
        ->where('product_id', $product->id)
        ->where('company_id', $this->company->id)
        ->with(['items.ingredient' => function ($query) {
            $query->where('branch_id', $this->branch->id);
        }])
        ->first();

    dump('Recipe found:', [
        'found' => $foundRecipe !== null,
        'items_count' => $foundRecipe ? $foundRecipe->items->count() : 0,
    ]);

    if ($foundRecipe && $foundRecipe->items->count() > 0) {
        $recipeItem = $foundRecipe->items->first();
        dump('Recipe item:', [
            'ingredient_id' => $recipeItem->raw_ingredient_id,
            'ingredient_loaded' => $recipeItem->relationLoaded('ingredient'),
            'ingredient_exists' => $recipeItem->ingredient !== null,
            'ingredient_branch_id' => $recipeItem->ingredient?->branch_id,
        ]);
    }

    // Crear pedido
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-DEBUG-2',
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $product->id,
        'name_snapshot' => 'Test Product',
        'quantity' => 2,
        'unit_price_snapshot' => 1000,
        'subtotal' => 2000,
    ]);

    // Stock antes
    dump('Stock antes:', (float) $ingredient->current_stock_base);

    // Llamar RecipeService directamente
    $recipeService = app(\Modules\Recipes\Domain\Services\RecipeService::class);
    $recipeService->deductInventoryForOrder($order);

    // Stock después
    $ingredient->refresh();
    dump('Stock después:', (float) $ingredient->current_stock_base);

    expect((float) $ingredient->current_stock_base)->toBe(9800.0);
});

test('debug: verificar que EventServiceProvider está registrado', function () {
    $providers = config('app.providers');
    
    $hasRecipeProvider = false;
    foreach ($providers as $provider) {
        if (str_contains($provider, 'RecipeEventServiceProvider')) {
            $hasRecipeProvider = true;
            dump('RecipeEventServiceProvider encontrado:', $provider);
            break;
        }
    }

    if (!$hasRecipeProvider) {
        dump('ERROR: RecipeEventServiceProvider NO está registrado en config/app.php');
        dump('Providers registrados:', array_filter($providers, fn($p) => str_contains($p, 'EventServiceProvider')));
    }

    expect($hasRecipeProvider)->toBeTrue('RecipeEventServiceProvider debe estar registrado');
});

test('debug: verificar que el evento OrderConfirmed dispara el listener', function () {
    // No fake events - queremos ver si se dispara realmente
    $category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Platos'],
        'sort_order' => 1,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Test Product'],
        'base_price' => 1000,
        'is_active' => true,
    ]);

    $ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'TEST-EVENT',
        'name_translations' => ['es' => 'Test Ingredient'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 10000,
        'cost_per_base_unit' => 1.0,
    ]);

    $recipe = ProductRecipe::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'yield_servings' => 1,
        'total_recipe_cost' => 0,
    ]);

    RecipeItem::createWithCalculation($recipe->id, $ingredient, 100.0, 0.0);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-EVENT-TEST',
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $product->id,
        'name_snapshot' => 'Test Product',
        'quantity' => 1,
        'unit_price_snapshot' => 1000,
        'subtotal' => 1000,
    ]);

    dump('Stock antes de disparar evento:', (float) $ingredient->current_stock_base);

    // Disparar evento
    $event = new OrderConfirmed($order);
    event($event);

    $ingredient->refresh();
    dump('Stock después de disparar evento:', (float) $ingredient->current_stock_base);

    expect((float) $ingredient->current_stock_base)->toBe(9900.0);
});
