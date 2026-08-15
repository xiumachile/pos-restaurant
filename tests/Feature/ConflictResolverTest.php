<?php

use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Sync\Domain\Entities\SyncQueue;
use Modules\Sync\Domain\Entities\SyncLog;
use Modules\Sync\Domain\Services\ConflictResolver;
use Modules\Sync\Domain\Enums\ResolutionStrategy;
use Modules\Sync\Domain\ValueObjects\SyncAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->resolver = new ConflictResolver();

    $this->company = Company::forceCreate([
        'tax_id' => '76.444.444-4',
        'legal_name' => 'Conflict Test SpA',
        'trade_name' => 'Conflict Test',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'CONF',
        'name' => 'Conflict Branch',
    ]);

    $this->user = User::forceCreate([
        'name' => 'Conflict User',
        'email' => 'conflict-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);
});

function createConflictOrder(array $overrides = []): Order
{
    return Order::create(array_merge([
        'company_id' => test()->company->id,
        'branch_id' => test()->branch->id,
        'waiter_id' => test()->user->id,
        'order_number' => 'ORD-CONF-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ], $overrides));
}

test('SERVER_WINS aplica datos del servidor al cliente', function () {
    $order = createConflictOrder([
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'sync_status' => 'pending',
        'version' => 1,
    ]);

    $queueItem = SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => $order->id,
        'entity_uuid' => $order->uuid,
        'action' => SyncAction::UPDATE,
        'payload' => [
            'status' => 'confirmed',
            'subtotal' => 1500,
        ],
        'version' => 1,
        'status' => 'pending',
    ]);

    $serverData = [
        'status' => 'preparing',
        'subtotal' => 2000,
        'version' => 2,
    ];

    $result = $this->resolver->resolve($queueItem, $serverData, ResolutionStrategy::SERVER_WINS);

    expect($result['resolved'])->toBeTrue()
        ->and($result['action_taken'])->toBe('server_data_applied');

    $order->refresh();
    expect($order->status->value)->toBe('preparing')
        ->and((float) $order->subtotal)->toBe(2000.0)
        ->and($order->version)->toBe(2)
        ->and($order->sync_status->value)->toBe('synced');
});

test('CLIENT_WINS marca para reintentar con datos del cliente', function () {
    $order = createConflictOrder([
        'status' => OrderStatus::DRAFT,
        'sync_status' => 'pending',
    ]);

    $queueItem = SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => $order->id,
        'entity_uuid' => $order->uuid,
        'action' => SyncAction::UPDATE,
        'payload' => ['status' => 'confirmed'],
        'version' => 1,
        'attempts' => 1,
        'status' => 'pending',
    ]);

    $serverData = ['status' => 'preparing', 'version' => 2];

    $result = $this->resolver->resolve($queueItem, $serverData, ResolutionStrategy::CLIENT_WINS);

    expect($result['resolved'])->toBeTrue()
        ->and($result['action_taken'])->toBe('client_data_preserved');

    $queueItem->refresh();
    expect($queueItem->status)->toBe('pending')
        ->and($queueItem->attempts)->toBe(0);

    $order->refresh();
    expect($order->sync_status->value)->toBe('pending');
});

test('MERGE fusiona campos no conflictivos', function () {
    $order = createConflictOrder([
        'notes' => 'Nota original',
        'sync_status' => 'pending',
    ]);

    $queueItem = SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => $order->id,
        'entity_uuid' => $order->uuid,
        'action' => SyncAction::UPDATE,
        'payload' => ['notes' => 'Nota del cliente'],
        'version' => 1,
        'status' => 'pending',
    ]);

    $serverData = [
        'notes' => 'Nota del servidor',
        'version' => 2,
    ];

    $result = $this->resolver->resolve($queueItem, $serverData, ResolutionStrategy::MERGE);

    expect($result['resolved'])->toBeTrue()
        ->and($result['action_taken'])->toBe('fields_merged');

    $order->refresh();
    expect($order->notes)->toContain('Nota del servidor')
        ->and($order->notes)->toContain('Nota del cliente');
});

test('MERGE falla si hay conflictos en campos críticos', function () {
    $order = createConflictOrder([
        'status' => OrderStatus::DRAFT,
        'sync_status' => 'pending',
    ]);

    $queueItem = SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => $order->id,
        'entity_uuid' => $order->uuid,
        'action' => SyncAction::UPDATE,
        'payload' => ['status' => 'confirmed', 'subtotal' => 1500],
        'version' => 1,
        'status' => 'pending',
    ]);

    $serverData = [
        'status' => 'preparing',
        'subtotal' => 2000,
        'version' => 2,
    ];

    $result = $this->resolver->resolve($queueItem, $serverData, ResolutionStrategy::MERGE);

    expect($result['resolved'])->toBeFalse()
        ->and($result['action_taken'])->toBe('merge_failed_conflicts')
        ->and($result['conflict_details'])->toHaveKeys(['status', 'subtotal']);
});

test('MANUAL marca para revisión humana', function () {
    $order = createConflictOrder(['sync_status' => 'pending']);

    $queueItem = SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => $order->id,
        'entity_uuid' => $order->uuid,
        'action' => SyncAction::UPDATE,
        'payload' => ['status' => 'confirmed'],
        'version' => 1,
        'status' => 'pending',
    ]);

    $serverData = ['status' => 'preparing', 'version' => 2];

    $result = $this->resolver->resolve($queueItem, $serverData, ResolutionStrategy::MANUAL);

    expect($result['resolved'])->toBeFalse()
        ->and($result['action_taken'])->toBe('marked_for_manual_review');

    $queueItem->refresh();
    expect($queueItem->status)->toBe('conflict')
        ->and($queueItem->conflict_data)->toBeArray();
});

test('detecta conflictos correctamente', function () {
    $order = createConflictOrder(['sync_status' => 'pending']);

    $queueItem = SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => $order->id,
        'entity_uuid' => $order->uuid,
        'action' => SyncAction::UPDATE,
        'payload' => [
            'status' => 'confirmed',
            'subtotal' => 1500,
            'notes' => 'Nota cliente',
        ],
        'version' => 1,
        'status' => 'pending',
    ]);

    $serverData = [
        'status' => 'preparing',
        'subtotal' => 2000,
        'notes' => 'Nota servidor',
        'version' => 2,
    ];

    $result = $this->resolver->resolve($queueItem, $serverData, ResolutionStrategy::MANUAL);

    expect($result['conflict_details'])->toHaveKeys(['status', 'subtotal'])
        ->and($result['conflict_details']['status']['client'])->toBe('confirmed')
        ->and($result['conflict_details']['status']['server'])->toBe('preparing');
});

test('maneja entidad inexistente gracefully', function () {
    $queueItem = SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => 999999,
        'entity_uuid' => Str::uuid(), // UUID válido
        'action' => SyncAction::UPDATE,
        'payload' => ['status' => 'confirmed'],
        'version' => 1,
        'status' => 'pending',
    ]);

    $serverData = ['status' => 'preparing', 'version' => 2];

    $result = $this->resolver->resolve($queueItem, $serverData, ResolutionStrategy::SERVER_WINS);

    expect($result['resolved'])->toBeFalse()
        ->and($result['action_taken'])->toBe('skip_entity_not_found');
});

test('registra resolución en sync_log', function () {
    $order = createConflictOrder(['sync_status' => 'pending']);

    $queueItem = SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => $order->id,
        'entity_uuid' => $order->uuid,
        'action' => SyncAction::UPDATE,
        'payload' => ['status' => 'confirmed'],
        'version' => 1,
        'status' => 'pending',
    ]);

    $serverData = ['status' => 'preparing', 'version' => 2];

    $this->resolver->resolve($queueItem, $serverData, ResolutionStrategy::SERVER_WINS);

    $log = SyncLog::where('entity_id', $order->id)
        ->where('entity_type', Order::class)
        ->latest('synced_at')
        ->first();
    
    expect($log)->not->toBeNull()
        ->and($log->action->value)->toBe('update')
        ->and($log->result)->toBe('success')
        ->and($log->conflict_data)->toBeArray();
});
