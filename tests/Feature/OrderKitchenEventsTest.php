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

test('C-01: OrderPreparationStarted tiene todos los campos de outbox', function () {
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

    $event = new OrderPreparationStarted($order);

    expect($event->event_uuid)->toBeString()
        ->and(\Illuminate\Support\Str::isUuid($event->event_uuid))->toBeTrue()
        ->and($event->order_uuid)->toBe($order->uuid)
        ->and($event->company_id)->toBe($order->company_id)
        ->and($event->branch_id)->toBe($order->branch_id)
        ->and($event->version)->toBe($order->version)
        ->and($event->occurred_at)->not->toBeEmpty();
});

test('C-01: OrderServed tiene todos los campos de outbox', function () {
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

    $event = new OrderServed($order);

    expect($event->event_uuid)->toBeString()
        ->and(\Illuminate\Support\Str::isUuid($event->event_uuid))->toBeTrue()
        ->and($event->order_uuid)->toBe($order->uuid)
        ->and($event->company_id)->toBe($order->company_id)
        ->and($event->branch_id)->toBe($order->branch_id)
        ->and($event->version)->toBe($order->version)
        ->and($event->occurred_at)->not->toBeEmpty();
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

test('C-02: Transición con estado desactualizado en memoria es rechazada gracias al bloqueo', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-005',
        'type' => 'dine_in',
        'status' => OrderStatus::CONFIRMED,
        'waiter_id' => $this->user->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    // Cocinero A ejecuta la transición exitosamente
    $this->stateMachine->transition($order, OrderStatus::PREPARING);
    
    // Cocinero B tiene una instancia desactualizada en memoria (status = CONFIRMED)
    // e intenta ejecutar la misma transición
    $order->status = OrderStatus::CONFIRMED; 

    // La máquina de estados debe bloquear la fila, leer el estado REAL (PREPARING),
    // y rechazar la transición porque PREPARING no puede pasar a PREPARING.
    expect(fn() => $this->stateMachine->transition($order, OrderStatus::PREPARING))
        ->toThrow(\Modules\Orders\Domain\Exceptions\InvalidOrderTransitionException::class);

    // Verificar que el estado en BD se mantiene íntegro y no fue corrompido
    $order->refresh();
    expect($order->status->value)->toBe('preparing');
});

test('C-02: El código utiliza lockForUpdate para prevenir race conditions', function () {
    $stateMachinePath = base_path('app/Modules/Orders/Domain/Services/OrderStateMachine.php');
    
    expect(file_exists($stateMachinePath))->toBeTrue('El archivo OrderStateMachine.php debe existir');
    
    $code = file_get_contents($stateMachinePath);
    
    expect(str_contains($code, 'lockForUpdate'))->toBeTrue('OrderStateMachine debe usar lockForUpdate para prevenir race conditions');
    expect(str_contains($code, 'DB::transaction'))->toBeTrue('OrderStateMachine debe usar DB::transaction para atomicidad');
});
