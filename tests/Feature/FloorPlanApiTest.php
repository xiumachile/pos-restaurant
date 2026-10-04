<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Tables\Domain\Entities\DiningZone;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\ValueObjects\TableShape;
use Modules\Tables\Domain\ValueObjects\TableStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'FP-API-' . uniqid(),
        'legal_name' => 'Floor Plan API Test',
        'trade_name' => 'FP API',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'FPAPI',
        'name' => 'FP API Branch',
    ]);

    $this->manager = User::create([
        'name' => 'FP Manager',
        'email' => 'fpapi-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);

    $this->token = JWTAuth::fromUser($this->manager);

    // Zona de prueba
    $this->zone = DiningZone::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'MAIN',
        'name_translations' => ['es' => 'Principal', 'zh' => '主厅'],
        'color' => '#f97316',
        'floor_level' => 0,
        'sort_order' => 1,
    ]);

    // Mesa de prueba
    $this->table = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'zone_id' => $this->zone->id,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Principal'],
        'table_number' => 'M-01',
        'capacity' => 4,
        'position_x' => 100,
        'position_y' => 100,
        'status' => TableStatus::Available->value,
    ]);
});

test('GET /api/v1/floor-plan retorna layout completo', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson('/api/v1/floor-plan');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'zones' => [
                    '*' => ['uuid', 'code', 'name_translations', 'color', 'tables_count'],
                ],
                'tables' => [
                    '*' => ['uuid', 'table_number', 'zone_uuid', 'position_x', 'position_y', 'rotation', 'shape', 'status'],
                ],
            ],
        ]);

    $data = $response->json('data');
    expect($data['zones'])->toHaveCount(1);
    expect($data['tables'])->toHaveCount(1);
    expect($data['zones'][0]['code'])->toBe('MAIN');
    expect($data['tables'][0]['position_x'])->toBe(100);
});

test('PUT /api/v1/floor-plan actualiza posiciones atómicamente', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->putJson('/api/v1/floor-plan', [
        'tables' => [
            [
                'uuid' => $this->table->uuid,
                'position_x' => 250,
                'position_y' => 300,
                'rotation' => 90,
                'shape' => 'round',
                'width' => null,
                'height' => null,
            ],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.tables_updated', 1);

    // Verificar en BD
    $this->table->refresh();
    expect($this->table->position_x)->toBe(250);
    expect($this->table->position_y)->toBe(300);
    expect($this->table->rotation)->toBe(90);
    expect($this->table->shape)->toBe(TableShape::Round);
});

test('PUT /api/v1/floor-plan rechaza mesa de otra sucursal', function () {
    // Crear otra sucursal + mesa
    $otherBranch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'OTHER',
        'name' => 'Other Branch',
    ]);

    $otherTable = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $otherBranch->id,
        'area_code' => 'X',
        'area_name_translations' => ['es' => 'X'],
        'table_number' => 'X-01',
        'capacity' => 4,
        'status' => TableStatus::Available->value,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->putJson('/api/v1/floor-plan', [
        'tables' => [
            [
                'uuid' => $otherTable->uuid,
                'position_x' => 100,
                'position_y' => 100,
                'rotation' => 0,
                'shape' => 'square',
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('tables.0.uuid');
});

test('PUT /api/v1/floor-plan valida shape inválido', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->putJson('/api/v1/floor-plan', [
        'tables' => [
            [
                'uuid' => $this->table->uuid,
                'position_x' => 100,
                'position_y' => 100,
                'rotation' => 0,
                'shape' => 'triangle', // inválido
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('tables.0.shape');
});

test('POST /api/v1/dining-zones crea zona correctamente', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->postJson('/api/v1/dining-zones', [
        'code' => 'TERRAZA',
        'name_translations' => ['es' => 'Terraza', 'zh' => '露台'],
        'color' => '#10b981',
        'floor_level' => 0,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.code', 'TERRAZA')
        ->assertJsonPath('data.color', '#10b981');

    $this->assertDatabaseHas('dining_zones', [
        'branch_id' => $this->branch->id,
        'code' => 'TERRAZA',
    ]);
});

test('POST /api/v1/dining-zones rechaza código duplicado', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->postJson('/api/v1/dining-zones', [
        'code' => 'MAIN', // ya existe
        'name_translations' => ['es' => 'Otra', 'zh' => '其他'],
        'color' => '#10b981',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

test('PATCH /api/v1/dining-zones/{uuid} actualiza color y nombre', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->patchJson("/api/v1/dining-zones/{$this->zone->uuid}", [
        'color' => '#ec4899',
        'name_translations' => ['es' => 'Salón VIP', 'zh' => 'VIP厅'],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.color', '#ec4899')
        ->assertJsonPath('data.name_translations.es', 'Salón VIP');

    $this->zone->refresh();
    expect($this->zone->color)->toBe('#ec4899');
});

test('DELETE /api/v1/dining-zones/{uuid} falla si tiene mesas', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->deleteJson("/api/v1/dining-zones/{$this->zone->uuid}");

    $response->assertStatus(409)
        ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'mesa'));
});

test('DELETE /api/v1/dining-zones/{uuid} funciona si no tiene mesas', function () {
    // Crear zona vacía
    $emptyZone = DiningZone::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'EMPTY',
        'name_translations' => ['es' => 'Vacía'],
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->deleteJson("/api/v1/dining-zones/{$emptyZone->uuid}");

    $response->assertStatus(200);
    $this->assertSoftDeleted('dining_zones', ['uuid' => $emptyZone->uuid]);
});
