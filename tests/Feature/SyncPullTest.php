<?php

use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Sync\Domain\Entities\SyncLog;
use Modules\Sync\Domain\Services\SyncService;
use Modules\Sync\Domain\Services\ServerDataProvider;
use Modules\Sync\Domain\Enums\ResolutionStrategy;
use Modules\Sync\Domain\ValueObjects\SyncStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::forceCreate([
        'tax_id' => '76.333.333-3',
        'legal_name' => 'Pull Test SpA',
        'trade_name' => 'Pull Test',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'PULL',
        'name' => 'Pull Branch',
    ]);

    $this->user = User::forceCreate([
        'name' => 'Pull User',
        'email' => 'pull-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->syncService = new SyncService();
});

test('pullChanges descarga cambios del servidor', function () {
    // Crear orden ya sincronizada (simula datos del servidor)
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-PULL-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
        'sync_status' => 'synced',
        'version' => 1,
    ]);

    $result = $this->syncService->pullChanges($this->branch->id);

    expect($result['processed'])->toBe(1)
        ->and($result['success'])->toBe(1)
        ->and($result['direction'])->toBe('pull');
});

test('pullChanges registra log con dirección pull', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-PULL-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 2000,
        'tax_amount' => 380,
        'discount_amount' => 0,
        'total' => 2380,
        'sync_status' => 'synced',
        'version' => 1,
    ]);

    $result = $this->syncService->pullChanges($this->branch->id);

    $log = SyncLog::where('sync_session_id', $result['session_id'])
        ->where('direction', 'pull')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->result)->toBe('success');
});

test('ServerDataProvider retorna entidades sincronizadas', function () {
    // Crear 2 órdenes sincronizadas
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-PROVIDER-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
        'sync_status' => 'synced',
    ]);

    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-PROVIDER-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 2000,
        'tax_amount' => 380,
        'discount_amount' => 0,
        'total' => 2380,
        'sync_status' => 'synced',
    ]);

    $provider = new ServerDataProvider();
    $changes = $provider->getChangesSince($this->branch->id);

    expect($changes->count())->toBe(2);
});

test('pullChanges aísla cambios por sucursal', function () {
    $otherBranch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'OTHER',
        'name' => 'Other Branch',
    ]);

    // Orden en otra sucursal
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $otherBranch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-OTHER-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
        'sync_status' => 'synced',
    ]);

    // Orden en sucursal original
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-LOCAL-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
        'sync_status' => 'synced',
    ]);

    $result = $this->syncService->pullChanges($this->branch->id);

    expect($result['processed'])->toBe(1);
});

test('pullChanges ignora entidades pendientes (no sincronizadas)', function () {
    // Orden pendiente (no debería aparecer en pull)
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-PENDING-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
        'sync_status' => 'pending',
    ]);

    $provider = new ServerDataProvider();
    $changes = $provider->getChangesSince($this->branch->id);

    expect($changes->count())->toBe(0);
});

test('getLastSyncTimestamp retorna null si no hay syncs previos', function () {
    $provider = new ServerDataProvider();
    $timestamp = $provider->getLastSyncTimestamp($this->branch->id);

    expect($timestamp)->toBeNull();
});
