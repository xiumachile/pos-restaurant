<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Tables\Domain\Entities\DiningZone;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\ValueObjects\TableShape;
use Modules\Tables\Domain\ValueObjects\TableStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'FP-' . uniqid(),
        'legal_name' => 'Floor Plan Test',
        'trade_name' => 'FP Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'FP',
        'name' => 'FP Branch',
    ]);
});

test('DiningZone se crea correctamente con todos los campos', function () {
    $zone = DiningZone::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'TERRAZA',
        'name_translations' => ['es' => 'Terraza', 'zh' => '露台'],
        'color' => '#10b981',
        'floor_level' => 0,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    expect($zone->id)->toBeInt();
    expect($zone->uuid)->toBeString();
    expect($zone->code)->toBe('TERRAZA');
    expect($zone->color)->toBe('#10b981');
    expect($zone->name_translations)->toBe(['es' => 'Terraza', 'zh' => '露台']);
    expect($zone->getName())->toBe('Terraza');
    expect($zone->is_active)->toBeTrue();
});

test('DiningZone tiene constraint unique en (branch_id, code)', function () {
    DiningZone::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'MAIN',
        'name_translations' => ['es' => 'Principal'],
    ]);

    $this->expectException(\Illuminate\Database\QueryException::class);

    DiningZone::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'MAIN', // duplicado
        'name_translations' => ['es' => 'Principal 2'],
    ]);
});

test('RestaurantTable acepta nuevos campos de posición', function () {
    $zone = DiningZone::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'BAR',
        'name_translations' => ['es' => 'Bar'],
    ]);

    $table = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'zone_id' => $zone->id,
        'area_code' => 'BAR',
        'area_name_translations' => ['es' => 'Bar'],
        'table_number' => 'B-99',
        'capacity' => 4,
        'position_x' => 150,
        'position_y' => 200,
        'rotation' => 90,
        'shape' => 'round',
        'width' => 120,
        'height' => 80,
        'status' => TableStatus::Available->value,
    ]);

    expect($table->position_x)->toBe(150);
    expect($table->position_y)->toBe(200);
    expect($table->rotation)->toBe(90);
    expect($table->shape)->toBe(TableShape::Round);
    expect($table->width)->toBe(120);
    expect($table->height)->toBe(80);
});

test('RestaurantTable tiene relación con DiningZone', function () {
    $zone = DiningZone::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'VIP',
        'name_translations' => ['es' => 'VIP'],
    ]);

    $table = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'zone_id' => $zone->id,
        'area_code' => 'VIP',
        'area_name_translations' => ['es' => 'VIP'],
        'table_number' => 'VIP-01',
        'capacity' => 8,
        'status' => TableStatus::Available->value,
    ]);

    expect($table->zone)->not->toBeNull();
    expect($table->zone->code)->toBe('VIP');

    // Relación inversa
    $zone->refresh();
    expect($zone->tables)->toHaveCount(1);
    expect($zone->activeTablesCount())->toBe(1);
});

test('RestaurantTable valida shape por constraint', function () {
    $this->expectException(\Illuminate\Database\QueryException::class);

    \Illuminate\Support\Facades\DB::table('restaurant_tables')->insert([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'area_code' => 'TEST',
        'area_name_translations' => json_encode(['es' => 'Test']),
        'table_number' => 'X-01',
        'capacity' => 4,
        'shape' => 'invalid_shape', // Esto debe fallar
        'status' => 'available',
        'sync_status' => 'pending',
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

test('RestaurantTable valida rotation por constraint', function () {
    $this->expectException(\Illuminate\Database\QueryException::class);

    \Illuminate\Support\Facades\DB::table('restaurant_tables')->insert([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'area_code' => 'TEST',
        'area_name_translations' => json_encode(['es' => 'Test']),
        'table_number' => 'X-02',
        'capacity' => 4,
        'rotation' => 45, // Inválido: debe ser 0, 90, 180, 270
        'status' => 'available',
        'sync_status' => 'pending',
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

test('Scopes inZone y active funcionan correctamente', function () {
    $zone1 = DiningZone::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'A',
        'name_translations' => ['es' => 'A'],
    ]);

    $zone2 = DiningZone::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'B',
        'name_translations' => ['es' => 'B'],
        'is_active' => false,
    ]);

    RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'zone_id' => $zone1->id,
        'area_code' => 'A',
        'area_name_translations' => ['es' => 'A'],
        'table_number' => 'A-01',
        'capacity' => 4,
        'status' => TableStatus::Available->value,
    ]);

    RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'zone_id' => $zone2->id,
        'area_code' => 'B',
        'area_name_translations' => ['es' => 'B'],
        'table_number' => 'B-01',
        'capacity' => 4,
        'status' => TableStatus::Available->value,
    ]);

    expect(RestaurantTable::inZone($zone1->id)->count())->toBe(1);
    expect(RestaurantTable::inZone($zone2->id)->count())->toBe(1);
    expect(DiningZone::active()->count())->toBe(1);
    expect(DiningZone::ordered()->first()->code)->toBe('A');
});

test('area_code legacy se mantiene funcionando después de migración', function () {
    // Crear mesa con area_code antiguo sin zone_id
    $table = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'area_code' => 'LEGACY',
        'area_name_translations' => ['es' => 'Legacy'],
        'table_number' => 'LEG-01',
        'capacity' => 4,
        'status' => TableStatus::Available->value,
    ]);

    // refresh() necesario para traer defaults de DB
    // (Laravel no carga defaults de PostgreSQL en create() automático)
    $table->refresh();

    expect($table->zone_id)->toBeNull();
    expect($table->area_code)->toBe('LEGACY');
    expect($table->position_x)->toBe(0);
    expect($table->position_y)->toBe(0);
    expect($table->shape)->toBe(TableShape::Square);

    // Scope legacy aún funciona
    expect(RestaurantTable::inArea('LEGACY')->count())->toBe(1);
});
