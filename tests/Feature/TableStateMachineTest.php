<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\Exceptions\InvalidTableStatusTransition;
use Modules\Tables\Domain\ValueObjects\TableStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'TABLES-001',
        'legal_name' => 'Test Tables Company',
        'trade_name' => 'Test Tables Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'TST-001',
        'name' => 'Test Branch',
    ]);

    $this->table = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón Principal', 'zh' => '主厅'],
        'table_number' => 'M-01',
        'capacity' => 4,
        'status' => 'available',
    ]);
});

test('una mesa nueva inicia en estado available', function () {
    expect($this->table->status)->toBe(TableStatus::Available);
    expect($this->table->isAvailable())->toBeTrue();
    expect($this->table->hasActiveOrder())->toBeFalse();
});

test('permite transicion de available a occupied con un pedido', function () {
    $this->table->occupy(12345);

    expect($this->table->status)->toBe(TableStatus::Occupied);
    expect($this->table->current_order_id)->toBe(12345);
    expect($this->table->hasActiveOrder())->toBeTrue();
});

test('deniega ocupar una mesa sin pedido valido', function () {
    $this->table->occupy(0);
})->throws(InvalidTableStatusTransition::class);

test('permite transicion de occupied a billing', function () {
    $this->table->occupy(12345);
    $this->table->requestBilling();

    expect($this->table->status)->toBe(TableStatus::Billing);
});

test('permite transicion de billing a available liberando el pedido', function () {
    $this->table->occupy(12345);
    $this->table->requestBilling();
    $this->table->free();

    expect($this->table->status)->toBe(TableStatus::Available);
    expect($this->table->current_order_id)->toBeNull();
    expect($this->table->hasActiveOrder())->toBeFalse();
});

test('deniega transicion directa de occupied a available', function () {
    $this->table->occupy(12345);
    $this->table->free();
})->throws(InvalidTableStatusTransition::class);

test('deniega transicion de billing a occupied', function () {
    $this->table->occupy(12345);
    $this->table->requestBilling();
    $this->table->occupy(67890);
})->throws(InvalidTableStatusTransition::class);

test('deniega ocupar una mesa ya ocupada', function () {
    $this->table->occupy(12345);
    $this->table->occupy(67890);
})->throws(InvalidTableStatusTransition::class);

test('permite transicion de available a maintenance', function () {
    $this->table->setMaintenance();

    expect($this->table->status)->toBe(TableStatus::Maintenance);
});

test('permite transicion de maintenance a available', function () {
    $this->table->setMaintenance();
    $this->table->enable();

    expect($this->table->status)->toBe(TableStatus::Available);
});

test('deniega ocupar una mesa en mantenimiento', function () {
    $this->table->setMaintenance();
    $this->table->occupy(12345);
})->throws(InvalidTableStatusTransition::class);

test('deniega enviar a mantenimiento una mesa ocupada', function () {
    $this->table->occupy(12345);
    $this->table->setMaintenance();
})->throws(InvalidTableStatusTransition::class);

test('el ciclo completo de una mesa funciona correctamente', function () {
    // available → occupied → billing → available
    expect($this->table->status)->toBe(TableStatus::Available);

    $this->table->occupy(100);
    expect($this->table->status)->toBe(TableStatus::Occupied);

    $this->table->requestBilling();
    expect($this->table->status)->toBe(TableStatus::Billing);

    $this->table->free();
    expect($this->table->status)->toBe(TableStatus::Available);
    expect($this->table->current_order_id)->toBeNull();
});

test('el nombre del area se traduce correctamente', function () {
    expect($this->table->translate('area_name_translations', 'es-CL'))->toBe('Salón Principal');
    expect($this->table->translate('area_name_translations', 'zh-CN'))->toBe('主厅');
});

test('los scopes filtran correctamente por estado', function () {
    // Crear una mesa ocupada
    $occupiedTable = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón Principal'],
        'table_number' => 'M-02',
        'capacity' => 2,
        'status' => 'occupied',
        'current_order_id' => 999,
    ]);

    expect(RestaurantTable::available()->count())->toBe(1);
    expect(RestaurantTable::occupied()->count())->toBe(1);
    expect(RestaurantTable::inArea('MAIN')->count())->toBe(2);
});
