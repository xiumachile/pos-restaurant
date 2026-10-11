<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\ValueObjects\BaseUnit;
use Modules\Recipes\Domain\ValueObjects\DimensionType;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create(['tax_id' => 'TEST-P001', 'legal_name' => 'Test Co', 'trade_name' => 'Test']);
    $this->branch = Branch::create([
        'company_id' => $this->company->id, 
        'code' => 'T1', 
        'name' => 'Test Branch',
        'allow_negative_stock' => false,
    ]);

    $this->ingredient = RawIngredient::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'sku' => 'ING-001',
        'name_translations' => ['es' => 'Harina de Trigo'],
        'dimension_type' => DimensionType::MASS,
        'base_unit' => BaseUnit::GRAM,
        'current_stock_base' => 1000.0, // 1000 gramos
        'committed_stock_base' => 200.0, // 200 gramos comprometidos
        'pending_incoming_base' => 500.0, // 500 gramos en camino
        'minimum_stock_base' => 100.0,
        'cost_per_base_unit' => 0.05,
        'is_active' => true,
    ]);
});

test('P0-01: Descontar stock físico actualiza solo current_stock_base y mantiene los demás intactos', function () {
    $initialCurrent = (float) $this->ingredient->current_stock_base;
    $initialCommitted = (float) $this->ingredient->committed_stock_base;
    $initialPending = (float) $this->ingredient->pending_incoming_base;

    $deductAmount = 300.0;

    // Ejecutar el descuento
    $this->ingredient->deductStock($deductAmount);

    // Recargar desde la base de datos para verificar persistencia
    $this->ingredient->refresh();

    // 1. El stock físico debe haber disminuido exactamente en la cantidad descontada
    expect((float) $this->ingredient->current_stock_base)->toBe($initialCurrent - $deductAmount);

    // 2. El stock comprometido NO debe haber cambiado
    expect((float) $this->ingredient->committed_stock_base)->toBe($initialCommitted);

    // 3. Las entradas pendientes NO deben haber cambiado
    expect((float) $this->ingredient->pending_incoming_base)->toBe($initialPending);
});

test('P0-01: Descontar más del stock disponible lanza excepción si allow_negative_stock es false', function () {
    $this->expectException(\Modules\Recipes\Domain\Exceptions\InsufficientIngredientStockException::class);
    
    // Intentar descontar más de lo que hay (1000 disponibles, intentar 1500)
    $this->ingredient->deductStock(1500.0);
});

test('P0-01: La operación es atómica: un error a mitad de camino hace rollback total', function () {
    $initialCurrent = (float) $this->ingredient->current_stock_base;

    // Simular un error dentro de la transacción
    try {
        DB::transaction(function () {
            $this->ingredient->deductStock(100.0);
            throw new \Exception("Error simulado post-descuento");
        });
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe("Error simulado post-descuento");
    }

    // El stock NO debe haber cambiado porque la transacción completa hizo rollback
    $this->ingredient->refresh();
    expect((float) $this->ingredient->current_stock_base)->toBe($initialCurrent);
});

test('P0-01: Permite stock negativo si la sucursal lo permite', function () {
    // Cambiar configuración de la sucursal
    $this->branch->allow_negative_stock = true;
    $this->branch->save();
    $this->ingredient->refresh(); // Refrescar relación

    $initialCurrent = (float) $this->ingredient->current_stock_base;
    $deductAmount = 1500.0; // Más de los 1000 disponibles

    // No debería lanzar excepción
    $this->ingredient->deductStock($deductAmount);
    
    $this->ingredient->refresh();
    expect((float) $this->ingredient->current_stock_base)->toBe($initialCurrent - $deductAmount); // Será -500.0
});
