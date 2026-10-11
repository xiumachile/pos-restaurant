<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Sync\Domain\ValueObjects\SyncStatus;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['broadcasting.default' => 'log']);

    $this->company = Company::create([
        'tax_id' => 'TEST-UX01',
        'legal_name' => 'Test Co',
        'trade_name' => 'Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'T1',
        'name' => 'Test Branch',
    ]);

    $this->user = User::create([
        'name' => 'Admin User',
        'email' => 'admin@test.test',
        'password' => bcrypt('pw'),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'admin',
    ]);

    $this->token = JWTAuth::fromUser($this->user);
});

test('UX-01: La API reporta PENDING cuando un pedido se crea (estado por defecto)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-UX01-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 5000,
        'tax_amount' => 950,
        'total' => 5950,
    ]);

    $response = $this->withHeaders(apiHeaders($this->token))
        ->getJson("/api/v1/orders/{$order->uuid}");

    $response->assertStatus(200)
        ->assertJsonPath('data.sync_status', 'pending');
});

test('UX-01: La API reporta SYNCED cuando el pedido fue marcado como sincronizado', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-UX01-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 5000,
        'tax_amount' => 950,
        'total' => 5950,
    ]);

    // Marcar como sincronizado
    $order->markAsSynced();
    
    // Verificar directamente en la BD sin pasar por el caster del modelo
    $dbStatus = DB::table('orders')->where('id', $order->id)->value('sync_status');
    expect($dbStatus)->toBe('synced', "La BD debe tener 'synced' después de markAsSynced()");

    $response = $this->withHeaders(apiHeaders($this->token))
        ->getJson("/api/v1/orders/{$order->uuid}");

    $response->assertStatus(200)
        ->assertJsonPath('data.sync_status', 'synced');
});

test('UX-01: La API reporta CONFLICT cuando hay un choque de versiones', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-UX01-003',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 5000,
        'tax_amount' => 950,
        'total' => 5950,
    ]);

    $order->markAsConflict();
    
    $dbStatus = DB::table('orders')->where('id', $order->id)->value('sync_status');
    expect($dbStatus)->toBe('conflict', "La BD debe tener 'conflict' después de markAsConflict()");

    $response = $this->withHeaders(apiHeaders($this->token))
        ->getJson("/api/v1/orders/{$order->uuid}");

    $response->assertStatus(200)
        ->assertJsonPath('data.sync_status', 'conflict');
});
