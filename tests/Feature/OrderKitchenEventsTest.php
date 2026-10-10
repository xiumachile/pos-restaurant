<?php

use Illuminate\Support\Facades\Event;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Events\OrderPreparationStarted;
use Modules\Orders\Domain\Events\OrderServed;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\Services\OrderStateMachine;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'TEST',
        'legal_name' => 'Test Co',
        'trade_name' => 'Test'
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'T1',
        'name' => 'Test Branch'
    ]);

    $this->user = User::create([
        'name' => 'Waiter',
        'email' => 'waiter@test.test',
        'password' => bcrypt('password'),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter'
    ]);

    $this->stateMachine = app(OrderStateMachine::class);
});

test('C-01: OrderPreparationStarted se dispara al transicionar a PREPARING', function () {
    Event::fake([OrderPreparationStarted::class]);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-001',
        'type' => 'dine_in',
        'status' => OrderStatus::CONFIRMED,
        'waiter_id' => $this->user->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    $this->stateMachine->transition($order, OrderStatus::PREPARING);

    Event::assertDispatched(OrderPreparationStarted::class, function ($event) use ($order) {
        return $event->order->id === $order->id
            && !empty($event->event_uuid)
            && $event->order_uuid === $order->uuid
            && $event->company_id === $order->company_id
            && $event->branch_id === $order->branch_id
            && $event->version === $order->version
            && !empty($event->occurred_at);
    });
});

test('C-01: OrderServed se dispara al transicionar a SERVED', function () {
    Event::fake([OrderServed::class]);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-002',
        'type' => 'dine_in',
        'status' => OrderStatus::READY,
        'waiter_id' => $this->user->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    $this->stateMachine->transition($order, OrderStatus::SERVED);

    Event::assertDispatched(OrderServed::class, function ($event) use ($order) {
        return $event->order->id === $order->id
            && !empty($event->event_uuid)
            && $event->order_uuid === $order->uuid
            && $event->company_id === $order->company_id
            && $event->branch_id === $order->branch_id
            && $event->version === $order->version
            && !empty($event->occurred_at);
    });
});

test('C-01: Todos los eventos de transición actualizan los timestamps correctamente', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-003',
        'type' => 'dine_in',
        'status' => OrderStatus::CONFIRMED,
        'waiter_id' => $this->user->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    // Transicionar a PREPARING
    $this->stateMachine->transition($order, OrderStatus::PREPARING);
    $order->refresh();

    expect($order->preparing_at)->not->toBeNull()
        ->and($order->status->value)->toBe('preparing');

    // Transicionar a READY
    $this->stateMachine->transition($order, OrderStatus::READY);
    $order->refresh();

    expect($order->ready_at)->not->toBeNull()
        ->and($order->status->value)->toBe('ready');

    // Transicionar a SERVED
    $this->stateMachine->transition($order, OrderStatus::SERVED);
    $order->refresh();

    expect($order->served_at)->not->toBeNull()
        ->and($order->status->value)->toBe('served');
});
