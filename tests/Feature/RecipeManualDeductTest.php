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
use Modules\Recipes\Domain\Services\RecipeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'SIMPLE-' . uniqid(),
        'legal_name' => 'Simple Test',
        'trade_name' => 'Simple Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'SMP',
        'name' => 'Simple Branch',
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

test('descuento manual de receta funciona', function () {
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

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-MANUAL',
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 2000,
        'tax_amount' => 380,
        'discount_amount' => 0,
        'total' => 2380,
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

    // Recargar order con items
    $order->load('items');
    
    dump('Order items count:', $order->items->count());
    dump('First item product_id:', $order->items->first()->product_id);

    expect((float) $ingredient->current_stock_base)->toBe(10000.0);

    // Llamar RecipeService directamente (sin evento)
    $recipeService = app(RecipeService::class);
    $recipeService->deductInventoryForOrder($order);

    $ingredient->refresh();
    dump('Stock después:', (float) $ingredient->current_stock_base);

    expect((float) $ingredient->current_stock_base)->toBe(9800.0);
});
