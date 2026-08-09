<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\ValueObjects\TableStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'API-TEST-001',
        'legal_name' => 'API Test Company',
        'trade_name' => 'API Test Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'API-001',
        'name' => 'API Test Branch',
    ]);

    $this->user = User::create([
        'name' => 'API Test User',
        'email' => 'api@test.com',
        'password' => 'password',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'admin',
    ]);
});

test('GET /api/v1/tables retorna mesas agrupadas por area', function () {
    RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón Principal'],
        'table_number' => 'M-01',
        'capacity' => 4,
    ]);

    RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'area_code' => 'TERRAZA',
        'area_name_translations' => ['es' => 'Terraza'],
        'table_number' => 'T-01',
        'capacity' => 2,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/tables');

    $response->assertOk()
        ->assertJsonCount(2, 'data') // 2 áreas
        ->assertJsonStructure([
            'data' => [
                '*' => ['area_code', 'area_name', 'tables'],
            ],
        ]);
});

test('POST /api/v1/tables crea una nueva mesa', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/tables', [
            'area_code' => 'VIP',
            'area_name_translations' => [
                'es' => 'Área VIP',
                'zh' => '贵宾区',
            ],
            'table_number' => 'VIP-01',
            'capacity' => 8,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.table_number', 'VIP-01')
        ->assertJsonPath('data.status', 'available');

    $this->assertDatabaseHas('restaurant_tables', [
        'table_number' => 'VIP-01',
        'branch_id' => $this->branch->id,
    ]);
});

test('PUT /api/v1/tables/{uuid}/status permite transicion valida', function () {
    $table = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón'],
        'table_number' => 'M-99',
        'capacity' => 4,
        'status' => 'available',
    ]);

    $response = $this->actingAs($this->user)
        ->putJson("/api/v1/tables/{$table->uuid}/status", [
            'status' => 'maintenance',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'maintenance');
});

test('PUT /api/v1/tables/{uuid}/status deniega transicion invalida', function () {
    $table = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón'],
        'table_number' => 'M-98',
        'capacity' => 4,
        'status' => 'occupied',
        'current_order_id' => 123,
    ]);

    $response = $this->actingAs($this->user)
        ->putJson("/api/v1/tables/{$table->uuid}/status", [
            'status' => 'available', // No permitido directamente desde occupied
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'invalid_status_transition');
});
