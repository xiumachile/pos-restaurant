<?php

use Modules\Audit\Domain\Entities\AuditLog;
use Modules\Audit\Domain\Services\AuditService;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::forceCreate([
        'tax_id' => '76.000.111-2',
        'legal_name' => 'Audit API Test SpA',
        'trade_name' => 'Audit API Test',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'AUDAPI',
        'name' => 'Audit API Branch',
    ]);

    $this->admin = User::forceCreate([
        'name' => 'Admin User',
        'email' => 'admin-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'admin',
    ]);

    $this->waiter = User::forceCreate([
        'name' => 'Waiter User',
        'email' => 'waiter-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->auditService = new AuditService();
});

test('GET /api/v1/audit-logs retorna lista para admin', function () {
    $this->actingAs($this->admin);
    
    // Crear un audit log
    $this->auditService->log(
        action: 'test_event',
        entityType: Order::class,
        entityId: 1,
        reason: 'Test'
    );

    $response = $this->actingAs($this->admin, 'api')
        ->getJson('/api/v1/audit-logs');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['data', 'total']]);
});

test('GET /api/v1/audit-logs deniega acceso a waiter', function () {
    $response = $this->actingAs($this->waiter, 'api')
        ->getJson('/api/v1/audit-logs');

    $response->assertStatus(403);
});

test('GET /api/v1/audit-logs filtra por action', function () {
    $this->actingAs($this->admin);

    // Crear audit logs de diferentes acciones
    $this->auditService->log(
        action: 'order_cancelled',
        entityType: Order::class,
        entityId: 1,
        reason: 'Test 1'
    );

    $this->auditService->log(
        action: 'discount_applied',
        entityType: Order::class,
        entityId: 2,
        reason: 'Test 2'
    );

    $response = $this->actingAs($this->admin, 'api')
        ->getJson('/api/v1/audit-logs?action=order_cancelled');

    $response->assertOk();
    
    $data = $response->json('data.data');
    expect($data)->toHaveCount(1);
    expect($data[0]['action'])->toBe('order_cancelled');
});

test('GET /api/v1/audit-logs/actions retorna lista de acciones', function () {
    $response = $this->actingAs($this->admin, 'api')
        ->getJson('/api/v1/audit-logs/actions');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data']);

    $actions = $response->json('data');
    expect($actions)->toHaveKeys(['order_cancelled', 'discount_applied', 'drawer_opened', 'price_changed']);
});

test('GET /api/v1/audit-logs/{uuid} retorna detalle', function () {
    $this->actingAs($this->admin);

    $log = $this->auditService->log(
        action: 'test_event',
        entityType: Order::class,
        entityId: 1,
        reason: 'Test detail'
    );

    $response = $this->actingAs($this->admin, 'api')
        ->getJson("/api/v1/audit-logs/{$log->uuid}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.uuid', $log->uuid)
        ->assertJsonPath('data.action', 'test_event');
});

test('GET /api/v1/audit-logs/{uuid} retorna 404 si no existe', function () {
    $response = $this->actingAs($this->admin, 'api')
        ->getJson('/api/v1/audit-logs/' . Str::uuid());

    $response->assertStatus(404);
});

test('sin autenticación retorna 401', function () {
    $response = $this->getJson('/api/v1/audit-logs');

    $response->assertStatus(401);
});
