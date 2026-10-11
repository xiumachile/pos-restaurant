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
use Modules\Catalog\Domain\Entities\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use App\Shared\Application\TenantContext;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Forzar driver de broadcasting a 'log' para evitar errores de Pusher en testing
    
    $this->company = Company::create(['tax_id' => 'TEST', 'legal_name' => 'Test Co', 'trade_name' => 'Test']);
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

    $this->ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'ING-001',
        'name_translations' => ['es' => 'Harina', 'zh' => '面粉'],
        'dimension_type' => 'mass',
        'base_unit' => 'g',
        'current_stock_base' => 5000.0,
        'minimum_stock_base' => 100.0,
        'cost_per_base_unit' => 0.5,
        'is_active' => true,
    ]);

    $this->ingredient2 = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'ING-002',
        'name_translations' => ['es' => 'Azúcar', 'zh' => '糖'],
        'dimension_type' => 'mass',
        'base_unit' => 'g',
        'current_stock_base' => 3000.0,
        'minimum_stock_base' => 50.0,
        'cost_per_base_unit' => 0.3,
        'is_active' => true,
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Pan Artesanal', 'zh' => '手工面包'],
        'base_price' => 2500,
        'is_active' => true,
    ]);

    $this->recipe = ProductRecipe::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'description' => 'Receta de pan artesanal / 手工面包配方',
        'yield_servings' => 1,
        'total_recipe_cost' => 0,
        'version' => 1,
    ]);

    RecipeItem::createWithCalculation(
        recipeId: $this->recipe->id,
        ingredient: $this->ingredient,
        quantityBase: 200.0,
        wastePercentage: 5.0
    );

    RecipeItem::createWithCalculation(
        recipeId: $this->recipe->id,
        ingredient: $this->ingredient2,
        quantityBase: 50.0,
        wastePercentage: 0.0
    );

    $this->recipe->recalculateTotalCost();
});

function createOrderWithItems($company, $branch, $product, $orderNumber, $quantity = 1) {
    $order = Order::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'order_number' => $orderNumber,
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 2500 * $quantity,
        'tax_amount' => 475 * $quantity,
        'total' => 2975 * $quantity,
    ]);

    OrderItem::create([
        'company_id' => $company->id,
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name_snapshot' => $product->name_translations['es'] ?? 'Producto',
        'unit_price_snapshot' => 2500,
        'quantity' => $quantity,
        'subtotal' => 2500 * $quantity,
        'tax_amount' => 475 * $quantity,
        'tax_rate_snapshot' => 19.0,
        'tax_name_snapshot' => 'IVA 19%',
    ]);

    return $order;
}

function assertFloatEquals(float $actual, float $expected, float $delta = 0.1, string $message = '') {
    expect(abs($actual - $expected))->toBeLessThan($delta, $message ?: "Expected {$expected}, got {$actual}");
}

test('I-01: Venta normal descuenta exactamente el consumo esperado', function () {
    $order = createOrderWithItems($this->company, $this->branch, $this->product, 'ORD-INV-001', 2);
    
    $stateMachine = app(OrderStateMachine::class);
    $stateMachine->transition($order, OrderStatus::CONFIRMED);

    $movements = RawIngredientMovement::where('reference_type', 'order')
        ->where('reference_id', $order->id)->get();

    expect($movements)->toHaveCount(2, "Deben existir 2 movimientos de consumo");
    
    $harinaMovement = $movements->firstWhere('raw_ingredient_id', $this->ingredient->id);
    expect($harinaMovement)->not->toBeNull("Debe existir movimiento de harina");
    assertFloatEquals((float)$harinaMovement->quantity_base, 420.0, 0.1, "Harina: 200g × 1.05 × 2 = 420g");
    expect($harinaMovement->recipe_version)->toBe(1);
    
    $this->ingredient->refresh();
    assertFloatEquals((float)$this->ingredient->current_stock_base, 4580.0, 0.1, "Stock harina: 5000 - 420 = 4580");
});

test('I-01: Un reintento NO duplica movimientos (idempotencia)', function () {
    $order = createOrderWithItems($this->company, $this->branch, $this->product, 'ORD-INV-002', 1);
    
    $stateMachine = app(OrderStateMachine::class);
    $stateMachine->transition($order, OrderStatus::CONFIRMED);

    // Simular reintento
    $recipeService = app(\Modules\Recipes\Domain\Services\RecipeService::class);
    $recipeService->deductInventoryForOrder($order);

    $movements = RawIngredientMovement::where('reference_type', 'order')
        ->where('reference_id', $order->id)->get();

    expect($movements)->toHaveCount(2, "No deben duplicarse movimientos en reintento");
    
    $this->ingredient->refresh();
    assertFloatEquals((float)$this->ingredient->current_stock_base, 4790.0, 0.1, "Stock descontado una sola vez: 5000 - 210");
});

test('I-01: Cancelación genera compensación auditable bilingüe', function () {
    $order = createOrderWithItems($this->company, $this->branch, $this->product, 'ORD-INV-003', 1);
    
    $stateMachine = app(OrderStateMachine::class);
    
    // Confirmar primero
    $stateMachine->transition($order, OrderStatus::CONFIRMED);
    
    // Verificar que se descontó stock
    $movementsConfirm = RawIngredientMovement::where('reference_type', 'order')
        ->where('reference_id', $order->id)->get();
    expect($movementsConfirm)->toHaveCount(2, "Debe haber 2 movimientos de confirmación");
    
    $stockAfterConfirm = (float) $this->ingredient->fresh()->current_stock_base;
    expect($stockAfterConfirm)->toBeLessThan(5000.0, "Stock debe disminuir tras confirmar");
    
    // Cancelar (con razón requerida)
    $stateMachine->transition($order, OrderStatus::CANCELLED, 'Cliente canceló / 客户取消');
    
    // Verificar movimientos de compensación
    $compensations = RawIngredientMovement::where('reference_type', 'order_cancellation')
        ->where('reference_id', $order->id)->get();

    expect($compensations)->toHaveCount(2, "Deben existir 2 movimientos de compensación");
    
    $this->ingredient->refresh();
    assertFloatEquals((float)$this->ingredient->current_stock_base, 5000.0, 0.1, "Stock restaurado tras cancelación");
    
    $this->ingredient2->refresh();
    assertFloatEquals((float)$this->ingredient2->current_stock_base, 3000.0, 0.1, "Stock azúcar restaurado");
    
    $compensation = $compensations->first();
    expect($compensation->reason)->toContain('[ES]') // Razón debe incluir español
        ->and($compensation->reason)->toContain('[ZH]'); // Razón debe incluir chino
});

test('I-01: Cambio de receta NO afecta movimientos anteriores', function () {
    $order = createOrderWithItems($this->company, $this->branch, $this->product, 'ORD-INV-004', 1);
    
    $stateMachine = app(OrderStateMachine::class);
    $stateMachine->transition($order, OrderStatus::CONFIRMED);

    $movement = RawIngredientMovement::where('reference_type', 'order')
        ->where('reference_id', $order->id)
        ->where('raw_ingredient_id', $this->ingredient->id)->first();

    expect($movement)->not->toBeNull("Debe existir movimiento");
    expect($movement->recipe_version)->toBe(1, "Movimiento debe tener versión 1 de receta");

    $recipeService = app(\Modules\Recipes\Domain\Services\RecipeService::class);
    $recipeService->updateRecipe($this->recipe, [
        ['raw_ingredient_id' => $this->ingredient->id, 'quantity_base_unit' => 300.0, 'waste_percentage' => 0],
    ]);

    $this->recipe->refresh();
    expect($this->recipe->version)->toBe(2, "Receta debe tener versión 2 tras actualización");
    
    $movement->refresh();
    expect($movement->recipe_version)->toBe(1, "Movimiento anterior mantiene versión 1");
    assertFloatEquals((float)$movement->quantity_base, 210.0, 0.1, "Cantidad no cambia tras actualización de receta");
});

test('I-01: Stock insuficiente previene descuento duplicado', function () {
    $order1 = createOrderWithItems($this->company, $this->branch, $this->product, 'ORD-INV-005A', 1);
    $this->ingredient->update(['current_stock_base' => 250.0]);
    $this->ingredient2->update(['current_stock_base' => 60.0]);
    
    $stateMachine = app(OrderStateMachine::class);
    $stateMachine->transition($order1, OrderStatus::CONFIRMED);
    
    $movements1 = RawIngredientMovement::where('reference_type', 'order')
        ->where('reference_id', $order1->id)
        ->where('raw_ingredient_id', $this->ingredient->id)->get();
    
    expect($movements1)->toHaveCount(1, "Primera orden genera 1 movimiento de harina");
    
    $this->ingredient->refresh();
    assertFloatEquals((float)$this->ingredient->current_stock_base, 40.0, 0.1, "Stock: 250 - 210 = 40");
    
    $order2 = createOrderWithItems($this->company, $this->branch, $this->product, 'ORD-INV-005B', 1);
    $stateMachine->transition($order2, OrderStatus::CONFIRMED);
    
    $movements2 = RawIngredientMovement::where('reference_type', 'order')
        ->where('reference_id', $order2->id)
        ->where('raw_ingredient_id', $this->ingredient->id)->get();
    
    expect($movements2)->toHaveCount(0, "Segunda orden no genera movimientos por stock insuficiente");
    
    $this->ingredient->refresh();
    assertFloatEquals((float)$this->ingredient->current_stock_base, 40.0, 0.1, "Stock no debe volverse negativo");
});
