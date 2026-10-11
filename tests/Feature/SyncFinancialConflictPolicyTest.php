<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\ValueObjects\PaymentStatus;
use Modules\Sync\Domain\Entities\SyncQueue;
use Modules\Sync\Domain\Enums\ResolutionStrategy;
use Modules\Sync\Domain\Services\ConflictResolver;

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

    $this->paymentMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'CASH',
        'name_translations' => ['es' => 'Efectivo', 'zh' => '现金'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    $this->resolver = app(ConflictResolver::class);
});

test('O-06: SERVER_WINS es bloqueado para entidades financieras (Payment)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    $payment = Payment::forceCreate([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'payment_method_id' => $this->paymentMethod->id,
        'user_id' => $this->user->id,
        'payment_number' => 'PAY-001',
        'amount' => 10000,
        'tip_amount' => 1000,
        'total_amount' => 11000,
        'method_code' => 'CASH',
        'status' => PaymentStatus::COMPLETED,
        'idempotency_key' => 'PAY-LOCAL-001',
    ]);

    $queueItem = SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Payment::class,
        'entity_id' => $payment->id,
        'entity_uuid' => $payment->uuid,
        'action' => 'update',
        'payload' => ['amount' => 10000, 'status' => 'completed'],
        'version' => 1,
        'status' => 'pending',
    ]);

    $serverData = ['amount' => 5000, 'status' => 'refunded', 'version' => 2];
    $result = $this->resolver->resolve($queueItem, $serverData, ResolutionStrategy::SERVER_WINS);

    expect($result['strategy'])->toBe('manual')
        ->and($result['resolved'])->toBeFalse()
        ->and($result['action_taken'])->toContain('manual_review');

    $queueItem->refresh();
    expect($queueItem->status)->toBe('conflict')
        ->and($queueItem->error_message)->toContain('Política');
});

test('O-06: Deduplicación por idempotency_key resuelve conflictos de Payment sin sobrescribir', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    $payment = Payment::forceCreate([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'payment_method_id' => $this->paymentMethod->id,
        'user_id' => $this->user->id,
        'payment_number' => 'PAY-002',
        'amount' => 10000,
        'tip_amount' => 1000,
        'total_amount' => 11000,
        'method_code' => 'CASH',
        'status' => PaymentStatus::COMPLETED,
        'idempotency_key' => 'PAY-DEDUP-001',
    ]);

    $queueItem = SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Payment::class,
        'entity_id' => $payment->id,
        'entity_uuid' => $payment->uuid,
        'action' => 'create',
        'payload' => ['amount' => 10000, 'idempotency_key' => 'PAY-DEDUP-001'],
        'version' => 1,
        'status' => 'pending',
    ]);

    $serverData = ['amount' => 10000, 'idempotency_key' => 'PAY-DEDUP-001', 'version' => 2];
    $result = $this->resolver->resolve($queueItem, $serverData, ResolutionStrategy::SERVER_WINS);

    expect($result['resolved'])->toBeTrue()
        ->and($result['action_taken'])->toContain('deduplicated');

    $payment->refresh();
    expect($payment->sync_status)->toBe('synced')
        ->and($payment->amount)->toBe(10000);
});

test('O-06: Order permite MERGE si solo hay conflictos en campos mergeables (notes)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-MERGE-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
        'notes' => 'Nota del servidor',
    ]);

    $queueItem = SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => $order->id,
        'entity_uuid' => $order->uuid,
        'action' => 'update',
        'payload' => ['notes' => 'Nota del cliente'],
        'version' => 1,
        'status' => 'pending',
    ]);

    $serverData = ['notes' => 'Nota del servidor', 'version' => 2];
    $result = $this->resolver->resolve($queueItem, $serverData, ResolutionStrategy::MERGE);

    expect($result['resolved'])->toBeTrue()
        ->and($result['action_taken'])->toContain('merged');

    $order->refresh();
    expect($order->notes)->toContain('Nota del servidor')
        ->and($order->notes)->toContain('Cliente');
});

test('O-06: Order fuerza MANUAL si hay conflicto en campos críticos (status, total)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-CONFLICT-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    $queueItem = SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => $order->id,
        'entity_uuid' => $order->uuid,
        'action' => 'update',
        'payload' => ['status' => 'confirmed', 'total' => 11900],
        'version' => 1,
        'status' => 'pending',
    ]);

    $serverData = ['status' => 'cancelled', 'total' => 0, 'version' => 2];
    $result = $this->resolver->resolve($queueItem, $serverData, ResolutionStrategy::MERGE);

    expect($result['resolved'])->toBeFalse()
        ->and($result['action_taken'])->toContain('manual');

    $queueItem->refresh();
    expect($queueItem->status)->toBe('conflict');
});
