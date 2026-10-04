<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\FloorPlan\Domain\Entities\FloorPlan;
use Modules\FloorPlan\Domain\Entities\FloorPlanObject;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'FP-NEW-' . uniqid(),
        'legal_name' => 'Floor Plan Redesign Test',
        'trade_name' => 'FP Redesign',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'FPRD',
        'name' => 'FP Redesign Branch',
    ]);
});

test('FloorPlan se crea con dimensiones y escala', function () {
    $plan = FloorPlan::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Salón Principal',
        'slug' => 'salon-principal',
        'width' => 1200,
        'height' => 1800,
        'scale' => 100.00,
    ]);

    expect($plan->id)->toBeInt();
    expect($plan->uuid)->toBeString();
    expect($plan->name)->toBe('Salón Principal');
    expect($plan->width)->toBe(1200);
    expect($plan->height)->toBe(1800);
    expect($plan->scale)->toBe('100.00');
    expect($plan->status)->toBe('draft');
    expect($plan->version)->toBe(1);
});

test('FloorPlan puede publicarse', function () {
    $plan = FloorPlan::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Terraza',
        'slug' => 'terraza',
    ]);

    expect($plan->isPublished())->toBeFalse();

    $plan->publish();
    $plan->save();

    expect($plan->isPublished())->toBeTrue();
    expect($plan->published_at)->not->toBeNull();
});

test('FloorPlan tiene objetos relacionados', function () {
    $plan = FloorPlan::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Salón VIP',
        'slug' => 'salon-vip',
    ]);

    $object = FloorPlanObject::create([
        'floor_plan_id' => $plan->id,
        'object_type' => 'table',
        'x' => 200,
        'y' => 300,
        'width' => 120,
        'height' => 120,
        'rotation' => 0,
        'z_index' => 1,
        'properties' => [
            'shape' => 'round',
            'capacity' => 4,
        ],
    ]);

    expect($object->id)->toBeInt();
    expect($object->uuid)->toBeString();
    expect($object->object_type)->toBe('table');
    expect($object->properties['shape'])->toBe('round');

    $plan->refresh();
    expect($plan->objects)->toHaveCount(1);
});

test('FloorPlanObject puede vincularse a mesa operativa', function () {
    $plan = FloorPlan::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Bar',
        'slug' => 'bar',
    ]);

    // Crear mesa operativa real
    $table = \Modules\Tables\Domain\Entities\RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'area_code' => 'BAR',
        'area_name_translations' => ['es' => 'Bar'],
        'table_number' => 'B-01',
        'capacity' => 2,
        'status' => 'available',
    ]);

    $object = FloorPlanObject::create([
        'floor_plan_id' => $plan->id,
        'object_type' => 'table',
        'object_key' => $table->uuid,
        'x' => 100,
        'y' => 100,
        'width' => 80,
        'height' => 80,
    ]);

    $linked = $object->linkedTable();
    expect($linked)->not->toBeNull();
    expect($linked->table_number)->toBe('B-01');
});

test('FloorPlanObject no table retorna null en linkedTable', function () {
    $plan = FloorPlan::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Test',
        'slug' => 'test',
    ]);

    $object = FloorPlanObject::create([
        'floor_plan_id' => $plan->id,
        'object_type' => 'plant',
        'x' => 400,
        'y' => 200,
        'width' => 80,
        'height' => 80,
        'properties' => ['variant' => 'large_indoor_plant'],
    ]);

    expect($object->linkedTable())->toBeNull();
});

test('FloorPlan slug único por sucursal', function () {
    FloorPlan::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Salón Principal',
        'slug' => 'salon-principal',
    ]);

    $this->expectException(\Illuminate\Database\QueryException::class);

    FloorPlan::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Otro Salón',
        'slug' => 'salon-principal', // duplicado
    ]);
});

test('Scopes de FloorPlan funcionan', function () {
    $draft = FloorPlan::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Draft Plan',
        'slug' => 'draft-plan',
    ]);

    $published = FloorPlan::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Published Plan',
        'slug' => 'published-plan',
    ]);
    $published->publish();
    $published->save();

    expect(FloorPlan::drafts()->count())->toBe(1);
    expect(FloorPlan::published()->count())->toBe(1);
});
