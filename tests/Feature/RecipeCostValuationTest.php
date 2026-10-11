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
use Modules\Recipes\Domain\ValueObjects\MovementType;
use Modules\Recipes\Domain\ValueObjects\BaseUnit;
use Modules\Recipes\Domain\ValueObjects\DimensionType;
use Modules\Catalog\Domain\Entities\Product;
use Illuminate\Support\Facades\Auth;
use App\Shared\Application\TenantContext;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['broadcasting.default' => 'log']);
    
    $this->company = Company::create(['tax_id' => 'TEST-I03', 'legal_name' => 'Test Co', 'trade_name' => 'Test']);
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

function assertI03FloatEquals(float $actual, float $expected, float $delta = 0.001, string $message = '') {
    expect(abs($actual - $expected))->toBeLessThan($delta, $message ?: "Expected {$expected}, got {$actual}");
}

test('I-03: El costo de consumo se congela usando Costo Promedio Ponderado (WAC) y no se altera retroactivamente', function () {
    // 1. Crear ingrediente con stock inicial y costo inicial
    $flour = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'FLOUR-WAC-001',
        'name_translations' => ['es' => 'Harina', 'zh' => '面粉'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 10000.0, // 10 kg iniciales
        'minimum_stock_base' => 100.0,
        'cost_per_base_unit' => 0.01, // $10 / 1000g = $0.01/g
        'is_active' => true,
    ]);

    // 2. SEGUNDA COMPRA: 10 kg (10,000 g) a $20/kg ($0.02/g)
    // WAC esperado: ((10000 * 0.01) + (10000 * 0.02)) / 20000 = (100 + 200) / 20000 = 0.015 $/g
    $flour->refresh();
    $flour->updateWeightedAverageCost(10000.0, 0.02);
    
    // Actualizar stock manualmente para reflejar la compra
    $flour->current_stock_base = 20000.0;
    $flour->save();

    $flour->refresh();
    assertI03FloatEquals((float)$flour->cost_per_base_unit, 0.015, 0.0001, "El costo WAC debe ser $0.015/g tras la segunda compra");

    // 3. Crear Producto y Receta (1 plato consume 500 g de harina)
    $product = Product::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Pan', 'zh' => '面包'],
        'base_price' => 5000,
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

    $recipeService = app(\Modules\Recipes\Domain\Services\RecipeService::class);
    $recipeService->updateRecipe($recipe, [
        [
            'raw_ingredient_id' => $flour->id,
            'quantity' => 500,
            'unit' => BaseUnit::GRAM->value,
            'waste_percentage' => 0.0
        ]
    ]);

    // 4. Vender 2 platos (Consumo total: 1000 g)
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-I03-001',
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
        'unit_price_snapshot' => 5000,
        'quantity' => 2,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'tax_rate_snapshot' => 19.0,
        'tax_name_snapshot' => 'IVA 19%',
    ]);

    $stateMachine = app(OrderStateMachine::class);
    $stateMachine->transition($order, OrderStatus::CONFIRMED);

    // 5. Verificar que el movimiento de consumo congeló el costo WAC ($0.015/g)
    $movement = RawIngredientMovement::where('reference_type', 'order')
        ->where('reference_id', $order->id)
        ->where('raw_ingredient_id', $flour->id)
        ->first();

    expect($movement)->not->toBeNull("Debe existir el movimiento de consumo");
    
    // DEBUG: Ver valores reales
    dump("DEBUG quantity_base: " . $movement->quantity_base);
    dump("DEBUG cost_per_base_unit_at_transaction: " . $movement->cost_per_base_unit_at_transaction);
    dump("DEBUG total_cost_at_transaction: " . $movement->total_cost_at_transaction);
    dump("DEBUG ingredient cost_per_base_unit: " . $flour->fresh()->cost_per_base_unit);
    
    assertI03FloatEquals((float)$movement->quantity_base, 1000.0, 0.1, "Cantidad consumida: 1000 g");
    assertI03FloatEquals((float)$movement->cost_per_base_unit_at_transaction, 0.015, 0.0001, "El costo unitario debe estar congelado en el WAC de $0.015/g");
    assertI03FloatEquals((float)$movement->total_cost_at_transaction, 15.00, 0.01, "El costo total del movimiento debe ser 1000g * $0.015 = $15.00");

    // 6. SIMULAR CAMBIO DE PRECIO FUTURO: El proveedor sube el precio a $30/kg ($0.03/g)
    $flour->refresh();
    $flour->updateWeightedAverageCost(5000.0, 0.03); // Nueva compra
    $flour->current_stock_base += 5000.0;
    $flour->save();

    // 7. Verificar RECONSTRUCCIÓN HISTÓRICA: El movimiento de la venta pasada NO se alteró
    $movement->refresh();
    assertI03FloatEquals((float)$movement->cost_per_base_unit_at_transaction, 0.015, 0.0001, "El costo histórico NO debe cambiar tras nuevas compras");
    assertI03FloatEquals((float)$movement->total_cost_at_transaction, 15.00, 0.01, "El costo total histórico debe permanecer intacto");
});
