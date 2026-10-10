<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Sync\Domain\Entities\SyncQueue;
use Modules\Sync\Domain\ValueObjects\SyncAction;
use Modules\Sync\Domain\Services\SyncService;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create(['tax_id' => 'TEST', 'legal_name' => 'Test Co', 'trade_name' => 'Test']);
    $this->branch = Branch::create(['company_id' => $this->company->id, 'code' => 'T1', 'name' => 'Test Branch']);
    
    $this->user = User::create([
        'name' => 'Cajero',
        'email' => 'cajero@test.test',
        'password' => bcrypt('pw'),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier'
    ]);

    $this->syncService = app(SyncService::class);
});

test('O-03: Un job atrapado en processing con lease expirado es recuperado a pending', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-STUCK-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    // Crear un job manualmente en estado 'processing' con lease expirado (hace 10 minutos)
    $stuckJob = SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => $order->id,
        'entity_uuid' => $order->uuid,
        'action' => SyncAction::CREATE,
        'payload' => $order->toArray(),
        'status' => 'processing',
        'processing_started_at' => now()->subMinutes(10),
        'lease_expires_at' => now()->subMinutes(5), // Lease expirado
        'attempts' => 1,
    ]);

    // Ejecutar recuperación
    $result = $this->syncService->recoverStuckJobs($this->branch->id);

    expect($result['recovered'])->toBe(1)
        ->and(empty($result['errors']))->toBeTrue();

    // Verificar que el job volvió a 'pending'
    $stuckJob->refresh();
    expect($stuckJob->status)->toBe('pending')
        ->and($stuckJob->processing_started_at)->toBeNull()
        ->and($stuckJob->lease_expires_at)->toBeNull()
        ->and($stuckJob->error_message)->toContain('recovered');
});

test('O-03: Un job en processing con lease VIGENTE NO es recuperado', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-STUCK-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    // Crear un job en estado 'processing' con lease vigente (expira en 5 minutos)
    $activeJob = SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => $order->id,
        'entity_uuid' => $order->uuid,
        'action' => SyncAction::CREATE,
        'payload' => $order->toArray(),
        'status' => 'processing',
        'processing_started_at' => now(),
        'lease_expires_at' => now()->addMinutes(5), // Lease vigente
        'attempts' => 1,
    ]);

    // Ejecutar recuperación
    $result = $this->syncService->recoverStuckJobs($this->branch->id);

    expect($result['recovered'])->toBe(0);

    // Verificar que el job sigue en 'processing'
    $activeJob->refresh();
    expect($activeJob->status)->toBe('processing')
        ->and($activeJob->lease_expires_at)->not->toBeNull();
});

test('O-03: El comando de consola sync:recover funciona correctamente', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-STUCK-003',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => $order->id,
        'entity_uuid' => $order->uuid,
        'action' => SyncAction::CREATE,
        'payload' => $order->toArray(),
        'status' => 'processing',
        'processing_started_at' => now()->subMinutes(10),
        'lease_expires_at' => now()->subMinutes(5),
        'attempts' => 1,
    ]);

    // Ejecutar comando de Artisan
    $this->artisan('sync:recover', ['--branch_id' => $this->branch->id])
        ->assertExitCode(0)
        ->expectsOutputToContain('trabajo(s) recuperado(s) exitosamente');

    // Verificar que el job fue recuperado
    $job = SyncQueue::where('entity_uuid', $order->uuid)->first();
    expect($job->status)->toBe('pending');
});

test('O-03: getSyncStats incluye el conteo de jobs stuck', function () {
    // Crear un job stuck
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-STUCK-004',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => $order->id,
        'entity_uuid' => $order->uuid,
        'action' => SyncAction::CREATE,
        'payload' => $order->toArray(),
        'status' => 'processing',
        'processing_started_at' => now()->subMinutes(10),
        'lease_expires_at' => now()->subMinutes(5),
        'attempts' => 1,
    ]);

    $stats = $this->syncService->getSyncStats($this->branch->id);

    expect($stats['stuck'])->toBe(1)
        ->and($stats['processing'])->toBe(1);
});
