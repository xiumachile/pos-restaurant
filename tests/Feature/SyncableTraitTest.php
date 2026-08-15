<?php

use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Sync\Domain\ValueObjects\SyncStatus;
use Modules\Sync\Domain\ValueObjects\SyncAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::forceCreate([
        'tax_id' => '76.777.777-7',
        'legal_name' => 'Sync Test SpA',
        'trade_name' => 'Sync Test',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'SYNC',
        'name' => 'Sync Branch',
    ]);

    $this->user = User::forceCreate([
        'name' => 'Sync User',
        'email' => 'sync-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);
});

test('Order se crea con sync_status pending por defecto', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SYNC-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    expect($order->sync_status)->toBe(SyncStatus::PENDING)
        ->and($order->version)->toBe(1)
        ->and($order->last_synced_at)->toBeNull();
});

test('Order actualizado incrementa version y marca pending', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SYNC-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    // Marcar como synced primero
    $order->markAsSynced();
    $order->refresh();
    
    expect($order->sync_status)->toBe(SyncStatus::SYNCED)
        ->and($order->version)->toBe(1);

    // Modificar (cambio relevante)
    $order->notes = 'Nota agregada offline';
    $order->save();
    $order->refresh();

    expect($order->sync_status)->toBe(SyncStatus::PENDING)
        ->and($order->version)->toBe(2);
});

test('markAsSynced actualiza estado y timestamp', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SYNC-003',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    expect($order->isSynced())->toBeFalse();

    $order->markAsSynced();
    $order->refresh();

    expect($order->isSynced())->toBeTrue()
        ->and($order->last_synced_at)->not->toBeNull();
});

test('markAsConflict marca correctamente', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SYNC-004',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    $order->markAsConflict();
    $order->refresh();

    expect($order->sync_status)->toBe(SyncStatus::CONFLICT);
});

test('needsSync retorna true solo para pending y failed', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SYNC-005',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    expect($order->needsSync())->toBeTrue(); // PENDING

    $order->markAsSynced();
    $order->refresh();
    expect($order->needsSync())->toBeFalse(); // SYNCED

    $order->markAsFailed();
    $order->refresh();
    expect($order->needsSync())->toBeTrue(); // FAILED

    $order->markAsConflict();
    $order->refresh();
    expect($order->needsSync())->toBeFalse(); // CONFLICT (requiere resolución manual)
});

test('cambios en sync metadata no incrementan version', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SYNC-006',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    $initialVersion = $order->version;

    // Llamar markAsSynced no debería incrementar versión
    $order->markAsSynced();
    $order->refresh();

    expect($order->version)->toBe($initialVersion);
});

test('scopes pending y synced funcionan', function () {
    // Crear 3 órdenes
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SYNC-007',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    $order2 = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SYNC-008',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 2000,
        'tax_amount' => 380,
        'discount_amount' => 0,
        'total' => 2380,
    ]);
    $order2->markAsSynced();

    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SYNC-009',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 3000,
        'tax_amount' => 570,
        'discount_amount' => 0,
        'total' => 3570,
    ]);

    expect(Order::pending()->count())->toBe(2);
    expect(Order::synced()->count())->toBe(1);
});

test('SyncStatus enum tiene métodos correctos', function () {
    expect(SyncStatus::PENDING->needsSync())->toBeTrue();
    expect(SyncStatus::SYNCED->needsSync())->toBeFalse();
    expect(SyncStatus::FAILED->needsSync())->toBeTrue();
    expect(SyncStatus::CONFLICT->needsSync())->toBeFalse();

    expect(SyncStatus::PENDING->label())->toBe('Pendiente');
    expect(SyncStatus::SYNCED->label())->toBe('Sincronizado');
    expect(SyncStatus::PENDING->labelZh())->toBe('待同步');
});

test('SyncAction enum representa las operaciones', function () {
    expect(SyncAction::CREATE->value)->toBe('create');
    expect(SyncAction::UPDATE->value)->toBe('update');
    expect(SyncAction::DELETE->value)->toBe('delete');
    
    expect(SyncAction::CREATE->label())->toBe('Creación');
    expect(SyncAction::CREATE->labelZh())->toBe('创建');
});
