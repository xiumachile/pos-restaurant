<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Orders\Domain\Services\OrderStateMachine;
use Modules\Recipes\Domain\Entities\ProductRecipe;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\Entities\RawIngredientMovement;
use Modules\Recipes\Domain\Entities\RecipeItem;
use Modules\Recipes\Domain\ValueObjects\MovementType;
use Modules\Recipes\Domain\ValueObjects\BaseUnit;
use Modules\Recipes\Domain\ValueObjects\DimensionType;
use Modules\Catalog\Domain\Entities\Product;
use Illuminate\Support\Facades\Auth;
use App\Shared\Application\TenantContext;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['broadcasting.default' => 'log']);
    
    $this->company = Company::create(['tax_id' => 'TEST-I02', 'legal_name' => 'Test Co', 'trade_name' => 'Test']);
    $this->branch = Branch::create(['company_id' => $this->company->id, 'code' => 'T1', 'name' => 'Test Branch']);
    
    $this->user = User::create([
        'name' => 'Cocinero',
        'email' => 'cocinero@test.test',
        'password' => bcrypt('pw'),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cook'
    ]);

    Auth::login($this->user);
    
    $tenantContext = app(TenantContext::class);
    $tenantContext->setCompany($this->company->id);
    $tenantContext->setBranch($this->branch->id);
});

function assertI02FloatEquals(float $actual, float $expected, float $delta = 0.1, string $message = '') {
    expect(abs($actual - $expected))->toBeLessThan($delta, $message ?: "Expected {$expected}, got {$actual}");
}

test('I-02: La cadena de unidades (Compra kg -> Stock g -> Receta kg -> Consumo g) es determinística y exacta', function () {
    // 1. Crear ingrediente con unidad base en GRAMOS
    $flour = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'FLOUR-001',
        'name_translations' => ['es' => 'Harina', 'zh' => '面粉'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM, // Unidad base: gramo
        'current_stock_base' => 0.0,
        'minimum_stock_base' => 100.0,
        'cost_per_base_unit' => 0.005, // 0.005 por gramo = 5.0 por kg
        'is_active' => true,
    ]);

    // 2. Simular COMPRA en KILOGRAMOS (10 kg)
    // El sistema debe convertir 10 kg a 10,000 g en el stock base
    $purchaseQtyKg = 10.0;
    $purchaseQtyBase = BaseUnit::convertToBase($purchaseQtyKg, BaseUnit::KILOGRAM, DimensionType::MASS);
    
    expect($purchaseQtyBase)->toBe(10000.0, "10 kg deben convertirse exactamente a 10,000 g");
    
    RawIngredientMovement::record(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        rawIngredientId: $flour->id,
        type: MovementType::InPurchase,
        quantityBase: $purchaseQtyBase,
        referenceType: 'purchase',
        referenceId: 1,
        reason: 'Compra de harina',
        originalQuantity: $purchaseQtyKg,
        originalUnit: BaseUnit::KILOGRAM->value
    );

    $flour->refresh();
    expect((float)$flour->current_stock_base)->toBe(10000.0, "El stock debe reflejar la conversión a gramos");

    // 3. Crear Producto y Receta
    $product = Product::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Pan', 'zh' => '面包'],
        'base_price' => 2500,
        'is_active' => true,
    ]);

    $recipe = ProductRecipe::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'description' => 'Pan',
        'yield_servings' => 1,
        'total_recipe_cost' => 0,
        'version' => 1,
    ]);

    // 4. Definir receta en KILOGRAMOS (0.25 kg por plato = 250 g)
    // El RecipeService debe convertir y guardar tanto el original como el base
    $recipeService = app(\Modules\Recipes\Domain\Services\RecipeService::class);
    $recipeService->updateRecipe($recipe, [
        [
            'raw_ingredient_id' => $flour->id,
            'quantity' => 0.25,       // 0.25 kg
            'unit' => BaseUnit::KILOGRAM->value, // kg
            'waste_percentage' => 0.0
        ]
    ]);

    // Verificar que RecipeItem guardó la conversión correctamente
    $recipeItem = $recipe->items()->first();
    expect((float)$recipeItem->original_quantity)->toBe(0.25, "Debe registrar la cantidad original");
    expect($recipeItem->original_unit)->toBe('kg', "Debe registrar la unidad original");
    expect((float)$recipeItem->quantity_base_unit)->toBe(250.0, "0.25 kg deben convertirse a 250 g base");
    expect((float)$recipeItem->effective_discount_base_quantity)->toBe(250.0, "Sin merma, efectivo = base");

    // 5. Vender 4 platos
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-I02-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    OrderItem::create([
        'company_id' => $this->company->id,
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name_snapshot' => 'Pan',
        'unit_price_snapshot' => 2500,
        'quantity' => 4, // 4 platos
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'tax_rate_snapshot' => 19.0,
        'tax_name_snapshot' => 'IVA 19%',
    ]);

    $stateMachine = app(OrderStateMachine::class);
    $stateMachine->transition($order, OrderStatus::CONFIRMED);

    // 6. Verificar disminución exacta de stock
    $flour->refresh();
    $expectedDecrease = 250.0 * 4; // 1000 g
    $expectedStock = 10000.0 - 1000.0; // 9000 g
    
    assertI02FloatEquals((float)$flour->current_stock_base, $expectedStock, 0.1, "El stock debe disminuir exactamente 1,000 g (4 platos × 250 g)");

    // 7. Verificar que el movimiento de consumo registró la trazabilidad
    $movement = RawIngredientMovement::where('reference_type', 'order')
        ->where('reference_id', $order->id)
        ->where('raw_ingredient_id', $flour->id)
        ->first();

    expect($movement)->not->toBeNull("Debe existir el movimiento de consumo");
    expect((float)$movement->quantity_base)->toBe(1000.0, "El movimiento debe registrar la cantidad base descontada (1000 g)");
    expect($movement->type)->toBe(MovementType::OutConsumption);
});

test('I-02: El sistema rechaza unidades incompatibles con la dimensión del ingrediente', function () {
    $flour = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'FLOUR-002',
        'name_translations' => ['es' => 'Harina', 'zh' => '面粉'],
        'dimension_type' => DimensionType::MASS, // Dimensión: Masa
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 1000.0,
        'minimum_stock_base' => 100.0,
        'cost_per_base_unit' => 0.005,
        'is_active' => true,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Pan', 'zh' => '面包'],
        'base_price' => 2500,
        'is_active' => true,
    ]);

    $recipe = ProductRecipe::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'description' => 'Pan',
        'yield_servings' => 1,
        'total_recipe_cost' => 0,
        'version' => 1,
    ]);

    // Intentar crear receta con unidad de VOLUMEN (Litros) para un ingrediente de MASA
    $recipeService = app(\Modules\Recipes\Domain\Services\RecipeService::class);
    
    expect(fn() => $recipeService->updateRecipe($recipe, [
        [
            'raw_ingredient_id' => $flour->id,
            'quantity' => 1.0,
            'unit' => BaseUnit::LITER->value, // ¡Incompatible!
            'waste_percentage' => 0.0
        ]
    ]))->toThrow(\InvalidArgumentException::class, 'Unidad incompatible');
});
