<?php

use App\Models\User;
use Modules\Tables\Domain\Entities\DiningZone;
use Modules\Tables\Domain\Entities\FloorPlanObject;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = auth()->login($this->user);

    $this->zone = DiningZone::create([
        'company_id' => $this->user->company_id,
        'branch_id' => $this->user->branch_id,
        'uuid' => Str::uuid()->toString(),
        'code' => 'MAIN',
        'name_translations' => ['es' => 'Salón Principal', 'zh' => '主厅'],
        'color' => '#f97316',
        'position_x' => 0,
        'position_y' => 0,
        'width' => 800,
        'height' => 600,
    ]);

    $this->table = RestaurantTable::create([
        'company_id' => $this->user->company_id,
        'branch_id' => $this->user->branch_id,
        'uuid' => Str::uuid()->toString(),
        'table_number' => 'M-01',
        'capacity' => 4,
        'status' => 'available',
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón Principal', 'zh' => '主厅'],
        'position_x' => 100,
        'position_y' => 100,
    ]);

    $this->floorObject = FloorPlanObject::create([
        'company_id' => $this->user->company_id,
        'branch_id' => $this->user->branch_id,
        'uuid' => Str::uuid()->toString(),
        'type' => 'table_square',
        'object_type' => 'table_square',
        'table_uuid' => $this->table->uuid,
        'zone_uuid' => $this->zone->uuid,
        'position_x' => 100,
        'position_y' => 100,
        'width' => 80,
        'height' => 80,
        'rotation' => 0,
        'z_index' => 10,
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
                'objects' => [
                    '*' => ['uuid', 'object_type', 'table_uuid', 'zone_uuid', 'position_x', 'position_y', 'width', 'height', 'rotation'],
                ],
                'available_tables',
            ],
        ]);

    $data = $response->json('data');
    expect($data['zones'])->toHaveCount(1);
    expect($data['objects'])->toHaveCount(1);
    expect($data['zones'][0]['code'])->toBe('MAIN');
    expect($data['objects'][0]['position_x'])->toBe(100);
    expect($data['objects'][0]['object_type'])->toBe('table_square');
});

test('PUT /api/v1/floor-plan actualiza posiciones atómicamente', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->putJson('/api/v1/floor-plan', [
        'objects' => [
            [
                'uuid' => $this->floorObject->uuid,
                'object_type' => 'table_square',
                'table_uuid' => $this->table->uuid,
                'zone_uuid' => $this->zone->uuid,
                'position_x' => 200,
                'position_y' => 200,
                'width' => 80,
                'height' => 80,
                'rotation' => 90,
            ],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.tables_updated', 1);

    $this->floorObject->refresh();
    expect($this->floorObject->position_x)->toBe(200);
    expect($this->floorObject->position_y)->toBe(200);
    expect($this->floorObject->rotation)->toBe(90);
    
    $this->table->refresh();
    expect($this->table->position_x)->toBe(200);
    expect($this->table->position_y)->toBe(200);
});

test('PUT /api/v1/floor-plan rechaza mesa de otra sucursal', function () {
    // ✅ Crear una segunda sucursal real con UUID para evitar FK violation
    $otherBranchId = DB::table('branches')->insertGetId([
        'uuid' => Str::uuid()->toString(),
        'company_id' => $this->user->company_id,
        'name' => 'Sucursal Secundaria',
        'code' => 'SEC',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherTable = RestaurantTable::create([
        'company_id' => $this->user->company_id,
        'branch_id' => $otherBranchId,
        'uuid' => Str::uuid()->toString(),
        'table_number' => 'M-99',
        'capacity' => 2,
        'status' => 'available',
        'area_code' => 'OTHER',
        'area_name_translations' => ['es' => 'Otra Zona', 'zh' => '其他区域'],
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->putJson('/api/v1/floor-plan', [
        'objects' => [
            [
                'uuid' => $this->floorObject->uuid,
                'object_type' => 'table_square',
                'table_uuid' => $otherTable->uuid,
                'position_x' => 100,
                'position_y' => 100,
                'width' => 80,
                'height' => 80,
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('objects.0.table_uuid');
});

test('PUT /api/v1/floor-plan valida object_type inválido', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->putJson('/api/v1/floor-plan', [
        'objects' => [
            [
                'uuid' => $this->floorObject->uuid,
                'object_type' => 'invalid_type_xyz',
                'table_uuid' => $this->table->uuid,
                'position_x' => 100,
                'position_y' => 100,
                'width' => 80,
                'height' => 80,
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('objects.0.object_type');
});

test('POST /api/v1/dining-zones crea zona correctamente', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->postJson('/api/v1/dining-zones', [
        'code' => 'TERRACE',
        'name_translations' => ['es' => 'Terraza', 'zh' => '露台'],
        'color' => '#10b981',
        'position_x' => 0,
        'position_y' => 0,
        'width' => 400,
        'height' => 300,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.code', 'TERRACE');
});

test('POST /api/v1/dining-zones rechaza código duplicado', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->postJson('/api/v1/dining-zones', [
        'code' => 'MAIN',
        'name_translations' => ['es' => 'Duplicado', 'zh' => '重复'],
        'color' => '#000000',
        'position_x' => 0,
        'position_y' => 0,
        'width' => 100,
        'height' => 100,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

test('PATCH /api/v1/dining-zones/{uuid} actualiza color y nombre', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->patchJson("/api/v1/dining-zones/{$this->zone->uuid}", [
        'color' => '#3b82f6',
        'name_translations' => ['es' => 'Salón Azul', 'zh' => '蓝色大厅'],
    ]);

    $response->assertStatus(200);
    $this->zone->refresh();
    expect($this->zone->color)->toBe('#3b82f6');
    expect($this->zone->name_translations['es'])->toBe('Salón Azul');
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
    $this->floorObject->delete();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->deleteJson("/api/v1/dining-zones/{$this->zone->uuid}");

    $response->assertStatus(200);
    
    // Verificar soft delete o hard delete
    $zoneExists = DiningZone::where('uuid', $this->zone->uuid)->exists();
    $zoneSoftDeleted = DiningZone::withTrashed()->where('uuid', $this->zone->uuid)->exists();
    
    if ($zoneSoftDeleted && !$zoneExists) {
        $this->assertTrue(true, 'Zona eliminada con soft delete');
    } elseif (!$zoneSoftDeleted) {
        $this->assertTrue(true, 'Zona eliminada completamente');
    } else {
        $this->fail('La zona no fue eliminada');
    }
});
