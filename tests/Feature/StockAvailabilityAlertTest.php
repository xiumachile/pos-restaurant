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
use Modules\Recipes\Domain\Services\StockAvailabilityService;
use Modules\Catalog\Domain\Entities\Product;
use Illuminate\Support\Facades\Auth;
use App\Shared\Application\TenantContext;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['broadcasting.default' => 'log']);

    $this->company = Company::create(['tax_id' => 'TEST-I04', 'legal_name' => 'Test Co', 'trade_name' => 'Test']);
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

function assertI04Float(float $actual, float $expected, float $delta = 0.1, string $msg = '') {
    expect(abs($actual - $expected))->toBeLessThan($delta, $msg ?: "Expected {$expected}, got {$actual}");
}

test('I-04: La alerta de stock se activa con el stock disponible real, no solo el físico', function () {
    // 1. Crear ingrediente con stock justo en el límite
    $flour = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'FLOUR-I04',
        'name_translations' => ['es' => 'Harina', 'zh' => '面粉'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 1000.0,   // 1 kg físico
        'committed_stock_base' => 0.0,
        'pending_incoming_base' => 0.0,
        'minimum_stock_base' => 500.0,     // Mínimo: 500 g
        'reorder_level_base' => 800.0,     // Reposición: 800 g
        'cost_per_base_unit' => 0.01,
        'is_active' => true,
    ]);

    $service = app(StockAvailabilityService::class);

    // 2. Verificar estado inicial: stock disponible = 1000 g, NO necesita reposición
    $summary = $service->getAvailabilitySummary($flour);
    assertI04Float($summary['available_stock'], 1000.0, 0.1, "Stock disponible inicial = físico");
    expect($summary['needs_reorder'])->toBeFalse("No necesita reposición con 1000 g disponibles");
    expect($summary['is_below_minimum'])->toBeFalse("No está bajo mínimo con 1000 g");

    // 3. Simular compromiso de stock (pedido confirmado que consume 400 g)
    $flour->commitStock(400.0);
    $flour->refresh();

    // Stock disponible = 1000 - 400 = 600 g
    $summary = $service->getAvailabilitySummary($flour);
    assertI04Float($summary['available_stock'], 600.0, 0.1, "Stock disponible = físico - comprometido");
    expect($summary['needs_reorder'])->toBeTrue("Necesita reposición: 600 g < 800 g (reorder level)");
    expect($summary['is_below_minimum'])->toBeFalse("Aún no bajo mínimo: 600 g > 500 g");

    // 4. Simular entrada pendiente (compra offline de 500 g sin sincronizar)
    $flour->pending_incoming_base = 500.0;
    $flour->save();
    $flour->refresh();

    // Stock disponible = 1000 - 400 + 500 = 1100 g
    $summary = $service->getAvailabilitySummary($flour);
    assertI04Float($summary['available_stock'], 1100.0, 0.1, "Stock disponible incluye entradas pendientes");
    expect($summary['needs_reorder'])->toBeFalse("No necesita reposición: 1100 g > 800 g");
});

test('I-04: El descuento físico libera el stock comprometido sin duplicar', function () {
    // 1. Crear ingrediente
    $flour = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'FLOUR-I04-02',
        'name_translations' => ['es' => 'Harina', 'zh' => '面粉'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 2000.0,
        'committed_stock_base' => 500.0,  // 500 g comprometidos
        'pending_incoming_base' => 0.0,
        'minimum_stock_base' => 500.0,
        'reorder_level_base' => 800.0,
        'cost_per_base_unit' => 0.01,
        'is_active' => true,
    ]);

    // Stock disponible = 2000 - 500 = 1500 g
    assertI04Float($flour->getAvailableStockBase(), 1500.0, 0.1, "Disponible antes del descuento");

    // 2. Simular descuento físico de los 500 g comprometidos
    $flour->current_stock_base = 1500.0; // Se descontó físicamente
    $flour->releaseCommittedStock(500.0); // Se libera el compromiso
    $flour->refresh();

    // Stock disponible = 1500 - 0 = 1500 g (NO 1000 g por doble descuento)
    assertI04Float($flour->getAvailableStockBase(), 1500.0, 0.1, "Disponible tras descuento: sin duplicación");
    assertI04Float((float)$flour->committed_stock_base, 0.0, 0.1, "Comprometido debe ser 0 tras liberación");
});

test('I-04: getCriticalStockAlerts retorna solo ingredientes con stock disponible bajo nivel de reposición', function () {
    // Ingrediente OK
    RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'SUGAR-I04',
        'name_translations' => ['es' => 'Azúcar', 'zh' => '糖'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 5000.0,
        'committed_stock_base' => 0.0,
        'pending_incoming_base' => 0.0,
        'minimum_stock_base' => 500.0,
        'reorder_level_base' => 1000.0,
        'cost_per_base_unit' => 0.005,
        'is_active' => true,
    ]);

    // Ingrediente CRÍTICO
    RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'BUTTER-I04',
        'name_translations' => ['es' => 'Mantequilla', 'zh' => '黄油'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 600.0,
        'committed_stock_base' => 200.0, // Disponible real: 400 g
        'pending_incoming_base' => 0.0,
        'minimum_stock_base' => 300.0,
        'reorder_level_base' => 500.0,   // 400 < 500 → ALERTA
        'cost_per_base_unit' => 0.03,
        'is_active' => true,
    ]);

    $service = app(StockAvailabilityService::class);
    $alerts = $service->getCriticalStockAlerts($this->company->id, $this->branch->id);

    expect(count($alerts))->toBe(1, "Solo un ingrediente debe estar en alerta");
    expect($alerts[0]['sku'])->toBe('BUTTER-I04');
    assertI04Float($alerts[0]['available_stock'], 400.0, 0.1, "Stock disponible real de mantequilla");
    expect($alerts[0]['needs_reorder'])->toBeTrue();
});
