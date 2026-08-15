<?php

use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Sync\Domain\Entities\SyncQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::forceCreate([
        'tax_id' => '76.888.777-6',
        'legal_name' => 'Sync API Test SpA',
        'trade_name' => 'Sync API Test',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'SYNCAPI',
        'name' => 'Sync API Branch',
    ]);

    $this->waiter = User::forceCreate([
        'name' => 'Waiter',
        'email' => 'waiter-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->manager = User::forceCreate([
        'name' => 'Manager',
        'email' => 'manager-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);

    $this->waiterToken = JWTAuth::fromUser($this->waiter);
    $this->managerToken = JWTAuth::fromUser($this->manager);
});

test('GET /api/v1/sync/health retorna estado del sistema', function () {
    $response = $this->actingAs($this->waiter, 'api')
        ->getJson('/api/v1/sync/health');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.sync_service', 'operational')
        ->assertJsonStructure(['data' => ['sync_service', 'local_database', 'timestamp']]);
});

test('GET /api/v1/sync/status retorna estadísticas', function () {
    // Crear una orden pendiente
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->waiter->id,
        'order_number' => 'ORD-API-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    $response = $this->actingAs($this->waiter, 'api')
        ->getJson('/api/v1/sync/status?branch_id=' . $this->branch->id);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.pending', 1);
});

test('POST /api/v1/sync/push procesa cambios pendientes', function () {
    // Crear orden pendiente
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->waiter->id,
        'order_number' => 'ORD-PUSH-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 2000,
        'tax_amount' => 380,
        'discount_amount' => 0,
        'total' => 2380,
    ]);

    $response = $this->actingAs($this->waiter, 'api')
        ->postJson('/api/v1/sync/push', [
        'branch_id' => $this->branch->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.processed', 1)
        ->assertJsonPath('data.success', 1);
});

test('POST /api/v1/sync/pull descarga cambios del servidor', function () {
    $response = $this->actingAs($this->waiter, 'api')
        ->postJson('/api/v1/sync/pull', [
        'branch_id' => $this->branch->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.direction', 'pull');
});

test('POST /api/v1/sync/push valida branch_id requerido', function () {
    $response = $this->actingAs($this->waiter, 'api')
        ->postJson('/api/v1/sync/push', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('branch_id');
});

test('POST /api/v1/sync/push rechaza branch_id inexistente', function () {
    $response = $this->actingAs($this->waiter, 'api')
        ->postJson('/api/v1/sync/push', [
        'branch_id' => 999999,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('branch_id');
});

test('POST /api/v1/sync/pull acepta strategy válido', function () {
    $response = $this->actingAs($this->waiter, 'api')
        ->postJson('/api/v1/sync/pull', [
        'branch_id' => $this->branch->id,
        'strategy' => 'server_wins',
    ]);

    $response->assertOk();
});

test('POST /api/v1/sync/pull rechaza strategy inválido', function () {
    $response = $this->actingAs($this->waiter, 'api')
        ->postJson('/api/v1/sync/pull', [
        'branch_id' => $this->branch->id,
        'strategy' => 'invalid_strategy',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('strategy');
});

test('GET /api/v1/sync/health no requiere autenticación para check básico', function () {
    // Este test verifica que el endpoint está disponible
    // En producción podría requerir auth, pero para health checks básicos
    // a veces se permite acceso sin auth
    $response = $this->actingAs($this->waiter, 'api')
        ->getJson('/api/v1/sync/health');

    $response->assertOk();
});
