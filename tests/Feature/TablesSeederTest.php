<?php

use Database\Seeders\TablesDemoSeeder;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Crear empresa y sucursales base para todos los tests
    $this->company = Company::create([
        'tax_id' => 'SEED-BASE-' . uniqid(),
        'legal_name' => 'Base Test Company',
        'trade_name' => 'Base Test Restaurant',
        'is_active' => true,
    ]);
});

test('TablesDemoSeeder crea mesas para todas las sucursales activas', function () {
    // Crear 2 sucursales
    Branch::create([
        'company_id' => $this->company->id,
        'code' => 'SEED-001',
        'name' => 'Seeder Branch 1',
        'is_active' => true,
    ]);

    Branch::create([
        'company_id' => $this->company->id,
        'code' => 'SEED-002',
        'name' => 'Seeder Branch 2',
        'is_active' => true,
    ]);

    // Ejecutar el seeder
    $this->seed(TablesDemoSeeder::class);

    // Verificar que se crearon mesas
    expect(RestaurantTable::count())->toBeGreaterThan(0);
    
    // Verificar que hay mesas en ambas sucursales
    $branchesWithTables = RestaurantTable::distinct()->pluck('branch_id')->count();
    expect($branchesWithTables)->toBe(2);
    
    // Cada sucursal debería tener 14 mesas (5 MAIN + 4 TERRAZA + 2 VIP + 3 BAR)
    expect(RestaurantTable::count())->toBe(28);
});

test('TablesDemoSeeder crea areas con traducciones bilingues', function () {
    Branch::create([
        'company_id' => $this->company->id,
        'code' => 'SEED-003',
        'name' => 'Seeder Branch 3',
        'is_active' => true,
    ]);

    $this->seed(TablesDemoSeeder::class);

    $table = RestaurantTable::first();

    // Asegurarse de que hay al menos una mesa
    expect($table)->not->toBeNull();
    expect($table->area_name_translations)->toBeArray();
    expect($table->area_name_translations)->toHaveKey('es');
    expect($table->area_name_translations)->toHaveKey('zh');
    expect($table->area_name_translations['es'])->not->toBeEmpty();
    expect($table->area_name_translations['zh'])->not->toBeEmpty();
});

test('todas las mesas inician en estado available', function () {
    Branch::create([
        'company_id' => $this->company->id,
        'code' => 'SEED-004',
        'name' => 'Seeder Branch 4',
        'is_active' => true,
    ]);

    $this->seed(TablesDemoSeeder::class);

    $total = RestaurantTable::count();
    $available = RestaurantTable::where('status', 'available')->count();
    
    // Verificar que hay mesas creadas
    expect($total)->toBeGreaterThan(0);
    
    // Verificar que TODAS están en estado available
    expect($available)->toBe($total);
});

test('TablesDemoSeeder ignora sucursales inactivas', function () {
    // Crear una sucursal activa y una inactiva
    Branch::create([
        'company_id' => $this->company->id,
        'code' => 'ACTIVE',
        'name' => 'Active Branch',
        'is_active' => true,
    ]);

    Branch::create([
        'company_id' => $this->company->id,
        'code' => 'INACTIVE',
        'name' => 'Inactive Branch',
        'is_active' => false,
    ]);

    $this->seed(TablesDemoSeeder::class);

    // Verificar que solo se crearon mesas para la sucursal activa
    $branchesWithTables = RestaurantTable::distinct()->pluck('branch_id');
    expect($branchesWithTables)->toHaveCount(1);
});
