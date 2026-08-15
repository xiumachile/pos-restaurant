<?php

use Modules\Orders\Domain\Entities\Order;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Sync\Domain\Entities\SyncQueue;
use Modules\Sync\Domain\Entities\SyncLog;
use Modules\Sync\Domain\Services\SyncService;
use Modules\Sync\Domain\ValueObjects\SyncStatus;
use Modules\Sync\Domain\ValueObjects\SyncAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::forceCreate([
        'tax_id' => '76.555.555-5',
        'legal_name' => 'Sync Service Test SpA',
        'trade_name' => 'Sync Service Test',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'SYNCS',
        'name' => 'Sync Service Branch',
    ]);

    $this->user = User::forceCreate([
        'name' => 'Sync User',
        'email' => 'syncservice-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->syncService = new SyncService();
});

test('pushChanges procesa cambios pendientes y los elimina de la cola', function () {
    // Crear orden (genera entrada automática en sync_queue vía trait)
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-PUSH-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    // Verificar que se registró en sync_queue
    expect(SyncQueue::where('branch_id', $this->branch->id)->count())->toBe(1);

    // Ejecutar push
    $result = $this->syncService->pushChanges($this->branch->id);

    expect($result['processed'])->toBe(1)
        ->and($result['success'])->toBe(1)
        ->and($result['failed'])->toBe(0)
        ->and($result['conflicts'])->toBe(0);

    // Verificar que la cola quedó vacía
    expect(SyncQueue::where('branch_id', $this->branch->id)->count())->toBe(0);

    // Verificar que la orden está marcada como synced
    $order->refresh();
    expect($order->sync_status)->toBe(SyncStatus::SYNCED)
        ->and($order->last_synced_at)->not->toBeNull();
});

test('pushChanges registra log de éxito en sync_log', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-PUSH-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 2000,
        'tax_amount' => 380,
        'discount_amount' => 0,
        'total' => 2380,
    ]);

    $result = $this->syncService->pushChanges($this->branch->id);

    // Verificar que se creó un log
    $log = SyncLog::where('sync_session_id', $result['session_id'])->first();
    
    expect($log)->not->toBeNull()
        ->and($log->direction)->toBe('push')
        ->and($log->result)->toBe('success')
        ->and($log->entity_type)->toBe(Order::class)
        ->and($log->action->value)->toBe('create');
});

test('pushChanges procesa múltiples cambios en orden cronológico', function () {
    // Crear 3 órdenes
    for ($i = 1; $i <= 3; $i++) {
        Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'waiter_id' => $this->user->id,
            'order_number' => "ORD-PUSH-00{$i}",
            'type' => OrderType::DINE_IN,
            'status' => OrderStatus::DRAFT,
            'subtotal' => 1000 * $i,
            'tax_amount' => 190 * $i,
            'discount_amount' => 0,
            'total' => 1190 * $i,
        ]);
    }

    expect(SyncQueue::where('branch_id', $this->branch->id)->count())->toBe(3);

    $result = $this->syncService->pushChanges($this->branch->id);

    expect($result['processed'])->toBe(3)
        ->and($result['success'])->toBe(3)
        ->and(SyncQueue::where('branch_id', $this->branch->id)->count())->toBe(0)
        ->and(SyncLog::where('sync_session_id', $result['session_id'])->count())->toBe(3);
});

test('pushChanges respeta el límite de cambios', function () {
    // Crear 5 órdenes
    for ($i = 1; $i <= 5; $i++) {
        Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'waiter_id' => $this->user->id,
            'order_number' => "ORD-LIMIT-00{$i}",
            'type' => OrderType::DINE_IN,
            'status' => OrderStatus::DRAFT,
            'subtotal' => 1000,
            'tax_amount' => 190,
            'discount_amount' => 0,
            'total' => 1190,
        ]);
    }

    // Procesar solo 2
    $result = $this->syncService->pushChanges($this->branch->id, 2);

    expect($result['processed'])->toBe(2)
        ->and(SyncQueue::where('branch_id', $this->branch->id)->count())->toBe(3); // Quedan 3
});

test('pushChanges marca como failed si la entidad no existe', function () {
    // Crear un registro en cola con entidad inexistente
    $queueItem = SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => 99999, // No existe
        'entity_uuid' => \Illuminate\Support\Str::uuid(),
        'action' => SyncAction::UPDATE,
        'payload' => ['notes' => 'test'],
        'version' => 1,
        'status' => 'pending',
    ]);

    $result = $this->syncService->pushChanges($this->branch->id);

    expect($result['processed'])->toBe(1)
        ->and($result['failed'])->toBe(1);

    // El item sigue en cola con estado failed
    $queueItem->refresh();
    expect($queueItem->status)->toBe('failed')
        ->and($queueItem->attempts)->toBe(1)
        ->and($queueItem->error_message)->toContain('not found');
});

test('getSyncStats retorna estadísticas correctas', function () {
    // Crear 2 órdenes
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-STATS-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-STATS-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 2000,
        'tax_amount' => 380,
        'discount_amount' => 0,
        'total' => 2380,
    ]);

    $stats = $this->syncService->getSyncStats($this->branch->id);

    expect($stats['pending'])->toBe(2)
        ->and($stats['processing'])->toBe(0)
        ->and($stats['failed'])->toBe(0);

    // Después de push
    $this->syncService->pushChanges($this->branch->id);
    $stats = $this->syncService->getSyncStats($this->branch->id);

    expect($stats['pending'])->toBe(0)
        ->and($stats['last_push'])->not->toBeNull();
});

test('pushChanges aísla cambios por sucursal', function () {
    // Crear otra sucursal
    $otherBranch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'OTHER',
        'name' => 'Other Branch',
    ]);

    $otherUser = User::forceCreate([
        'name' => 'Other User',
        'email' => 'other-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $otherBranch->id,
        'role' => 'waiter',
    ]);

    // Crear orden en otra sucursal
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $otherBranch->id,
        'waiter_id' => $otherUser->id,
        'order_number' => 'ORD-OTHER-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    // Crear orden en sucursal original
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
    ]);

    // Push solo para la sucursal original
    $result = $this->syncService->pushChanges($this->branch->id);

    expect($result['processed'])->toBe(1);

    // La otra sucursal sigue con pendiente
    expect(SyncQueue::where('branch_id', $otherBranch->id)->count())->toBe(1);
    expect(SyncQueue::where('branch_id', $this->branch->id)->count())->toBe(0);
});

test('pushChanges maneja updates en cola', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-UPDATE-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    // Push inicial (procesa el create)
    $this->syncService->pushChanges($this->branch->id);

    // Actualizar la orden (genera nuevo item en cola)
    $order->notes = 'Actualizado offline';
    $order->save();

    expect(SyncQueue::where('branch_id', $this->branch->id)->count())->toBe(1);
    expect(SyncQueue::first()->action->value)->toBe('update');

    // Push del update
    $result = $this->syncService->pushChanges($this->branch->id);

    expect($result['success'])->toBe(1);
    expect(SyncQueue::where('branch_id', $this->branch->id)->count())->toBe(0);

    $order->refresh();
    expect($order->sync_status)->toBe(SyncStatus::SYNCED);
});
