<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Companies\Domain\Entities\Company;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Recipes\Domain\Entities\ProductRecipe;
use Modules\Recipes\Domain\Entities\RecipeItem;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\ValueObjects\BaseUnit;
use Modules\Recipes\Domain\ValueObjects\DimensionType;
use Modules\Recipes\Domain\Services\StockAvailabilityService;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create(['tax_id' => 'TEST-P003', 'legal_name' => 'Test Co', 'trade_name' => 'Test']);
    $this->branch = Branch::create(['company_id' => $this->company->id, 'code' => 'T1', 'name' => 'Test Branch']);

    // Ingrediente: Harina (1000g disponibles, costo 0.05 por gramo)
    $this->flour = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'FLOUR-001',
        'name_translations' => ['es' => 'Harina'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 1000.0,
        'committed_stock_base' => 0.0,
        'pending_incoming_base' => 0.0,
        'minimum_stock_base' => 100.0,
        'cost_per_base_unit' => 0.05,
        'is_active' => true,
    ]);

    // Producto: Pan (requiere 200g de harina)
    $this->product = Product::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name_translations' => ['es' => 'Pan'],
        'base_price' => 1000,
        'is_active' => true,
    ]);

    // Receta: 1 Pan = 200g Harina
    $this->recipe = ProductRecipe::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'version' => 1,
        'is_active' => true,
    ]);

    // USAR EL PATRÓN CORRECTO DEL PROYECTO
    RecipeItem::createWithCalculation(
        recipeId: $this->recipe->id,
        ingredient: $this->flour,
        quantityBase: 200.0,
        wastePercentage: 0.0
    );

    $this->stockService = app(StockAvailabilityService::class);
});

test('P0-03: Un pedido confirmado NO duplica el stock comprometido tras el descuento físico', function () {
    // 1. Estado inicial
    expect((float) $this->flour->current_stock_base)->toBe(1000.0);
    expect((float) $this->flour->committed_stock_base)->toBe(0.0);

    // 2. Crear y confirmar pedido (esto dispara el descuento físico)
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-P003-001',
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    // PATRÓN EXACTO DE RecipeOrderIntegrationTest.php
    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $this->product->id,
        'name_snapshot' => 'Pan',
        'quantity' => 1,
        'unit_price_snapshot' => 1000,
        'subtotal' => 1000,
    ]);

    // Simular el descuento físico que hace DeductRecipeOnOrderConfirm
    $this->flour->current_stock_base = 1000.0 - 200.0; // 800g restantes
    $this->flour->save();

    // 3. Recalcular stock comprometido
    $this->stockService->recalculateCommittedStock($this->flour->fresh());
    $this->flour->refresh();

    // 4. Validaciones P0-03:
    // El stock físico debe ser 800g (ya descontado)
    expect((float) $this->flour->current_stock_base)->toBe(800.0);
    
    // El stock comprometido debe ser 0g, porque el pedido YA está CONFIRMED 
    // y por lo tanto YA fue descontado físicamente. No debe contarse dos veces.
    expect((float) $this->flour->committed_stock_base)->toBe(0.0);

    // El stock disponible debe ser 800g (800 - 0 + 0)
    expect($this->flour->getAvailableStockBase())->toBe(800.0);
});

test('P0-03: Suma correctamente múltiples componentes del mismo ingrediente en una receta', function () {
    // Agregar una segunda línea de harina a la misma receta (ej. 50g adicionales)
    RecipeItem::createWithCalculation(
        recipeId: $this->recipe->id,
        ingredient: $this->flour,
        quantityBase: 50.0,
        wastePercentage: 0.0
    );

    // Ahora 1 Pan requiere 250g de harina en total (200 + 50)
    
    // Crear pedido DRAFT (aún no confirmado, por lo tanto comprometido)
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-P003-002',
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $this->product->id,
        'name_snapshot' => 'Pan',
        'quantity' => 1,
        'unit_price_snapshot' => 1000,
        'subtotal' => 1000,
    ]);

    // Recalcular stock comprometido
    $this->stockService->recalculateCommittedStock($this->flour->fresh());
    $this->flour->refresh();

    // Debe comprometer 250g (200 + 50), no solo 200g (el bug de ->first())
    expect((float) $this->flour->committed_stock_base)->toBe(250.0);
    
    // Stock disponible = 1000 (físico) - 250 (comprometido) + 0 (pendiente entrada) = 750g
    expect($this->flour->getAvailableStockBase())->toBe(750.0);
});
