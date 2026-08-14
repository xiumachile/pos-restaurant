<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Orders\Domain\Services\OrderStateMachine;
use Modules\Orders\Domain\Exceptions\InvalidOrderTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Domain\Entities\Tax;
use Modules\Tax\Domain\ValueObjects\TaxType;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'ORD-TEST-' . uniqid(),
        'legal_name' => 'Order Test Company',
        'trade_name' => 'Order Test Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'ORD-001',
        'name' => 'Order Test Branch',
    ]);

    $this->user = User::create([
        'name' => 'Order Test User',
        'email' => 'order@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'admin',
    ]);

    $this->stateMachine = app(OrderStateMachine::class);

    // Crear Tax default (IVA 19%) para la empresa
    $this->iva19 = Tax::create([
        'company_id' => $this->company->id,
        'name' => 'IVA 19%',
        'code' => 'IVA',
        'type' => TaxType::PERCENT,
        'rate' => 19.00,
        'is_default' => true,
        'is_active' => true,
    ]);
});

function createOrder(array $overrides = []): Order
{
    return Order::create(array_merge([
        'company_id' => test()->company->id,
        'branch_id' => test()->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'waiter_id' => test()->user->id,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 0,
    ], $overrides));
}

// ============================================
// Estado inicial
// ============================================

test('un pedido nuevo inicia en estado draft', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
    ]);

    expect($order->status)->toBe(OrderStatus::DRAFT);
    expect($order->isEditable())->toBeTrue();
    expect($order->isActive())->toBeTrue();
});

// ============================================
// Transiciones válidas
// ============================================

test('permite transicion de draft a confirmed', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
    ]);

    $result = $this->stateMachine->transition($order, OrderStatus::CONFIRMED);

    expect($result->status)->toBe(OrderStatus::CONFIRMED);
    expect($result->confirmed_at)->not->toBeNull();
});

test('permite transicion de confirmed a preparing', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'status' => OrderStatus::CONFIRMED,
        'confirmed_at' => now(),
    ]);

    $result = $this->stateMachine->transition($order, OrderStatus::PREPARING);

    expect($result->status)->toBe(OrderStatus::PREPARING);
});

test('permite transicion de preparing a ready', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'status' => OrderStatus::PREPARING,
    ]);

    $result = $this->stateMachine->transition($order, OrderStatus::READY);

    expect($result->status)->toBe(OrderStatus::READY);
});

test('permite transicion de ready a served', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'status' => OrderStatus::READY,
    ]);

    $result = $this->stateMachine->transition($order, OrderStatus::SERVED);

    expect($result->status)->toBe(OrderStatus::SERVED);
    expect($result->served_at)->not->toBeNull();
});

test('permite transicion de served a paid', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'status' => OrderStatus::SERVED,
    ]);

    $result = $this->stateMachine->transition($order, OrderStatus::PAID);

    expect($result->status)->toBe(OrderStatus::PAID);
    expect($result->paid_at)->not->toBeNull();
});

test('permite transicion de paid a closed', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'status' => OrderStatus::PAID,
    ]);

    $result = $this->stateMachine->transition($order, OrderStatus::CLOSED);

    expect($result->status)->toBe(OrderStatus::CLOSED);
    expect($result->closed_at)->not->toBeNull();
});

test('el ciclo completo de un pedido funciona correctamente', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
    ]);

    // draft → confirmed
    $this->stateMachine->transition($order, OrderStatus::CONFIRMED);
    expect($order->status)->toBe(OrderStatus::CONFIRMED);

    // confirmed → preparing
    $this->stateMachine->transition($order, OrderStatus::PREPARING);
    expect($order->status)->toBe(OrderStatus::PREPARING);

    // preparing → ready
    $this->stateMachine->transition($order, OrderStatus::READY);
    expect($order->status)->toBe(OrderStatus::READY);

    // ready → served
    $this->stateMachine->transition($order, OrderStatus::SERVED);
    expect($order->status)->toBe(OrderStatus::SERVED);

    // served → paid
    $this->stateMachine->transition($order, OrderStatus::PAID);
    expect($order->status)->toBe(OrderStatus::PAID);

    // paid → closed
    $this->stateMachine->transition($order, OrderStatus::CLOSED);
    expect($order->status)->toBe(OrderStatus::CLOSED);
});

// ============================================
// Transiciones inválidas
// ============================================

test('deniega transicion directa de draft a preparing', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
    ]);

    expect(fn () => $this->stateMachine->transition($order, OrderStatus::PREPARING))
        ->toThrow(InvalidOrderTransitionException::class);
});

test('deniega transicion de confirmed a ready', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'status' => OrderStatus::CONFIRMED,
    ]);

    expect(fn () => $this->stateMachine->transition($order, OrderStatus::READY))
        ->toThrow(InvalidOrderTransitionException::class);
});

test('deniega transicion de served a closed', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'status' => OrderStatus::SERVED,
    ]);

    expect(fn () => $this->stateMachine->transition($order, OrderStatus::CLOSED))
        ->toThrow(InvalidOrderTransitionException::class);
});

test('deniega transicion desde closed a cualquier estado', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'status' => OrderStatus::CLOSED,
    ]);

    foreach ([OrderStatus::DRAFT, OrderStatus::CONFIRMED, OrderStatus::CANCELLED] as $invalidStatus) {
        expect(fn () => $this->stateMachine->transition($order, $invalidStatus))
            ->toThrow(InvalidOrderTransitionException::class);
    }
});

// ============================================
// Cancelación
// ============================================

test('permite cancelar un pedido en draft con razon', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
    ]);

    $result = $this->stateMachine->transition($order, OrderStatus::CANCELLED, 'Cliente cambió de opinión');

    expect($result->status)->toBe(OrderStatus::CANCELLED);
    expect($result->cancellation_reason)->toBe('Cliente cambió de opinión');
    expect($result->cancelled_at)->not->toBeNull();
});

test('deniega cancelar sin razon', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
    ]);

    expect(fn () => $this->stateMachine->transition($order, OrderStatus::CANCELLED))
        ->toThrow(InvalidOrderTransitionException::class);
});

test('deniega cancelar un pedido cerrado', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'status' => OrderStatus::CLOSED,
    ]);

    expect(fn () => $this->stateMachine->transition($order, OrderStatus::CANCELLED, 'Error'))
        ->toThrow(InvalidOrderTransitionException::class);
});

// ============================================
// Editabilidad
// ============================================

test('un pedido en draft es editable', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
    ]);

    expect($this->stateMachine->canModifyItems($order))->toBeTrue();
});

test('un pedido confirmado no es editable', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'status' => OrderStatus::CONFIRMED,
    ]);

    expect($this->stateMachine->canModifyItems($order))->toBeFalse();
});

// ============================================
// Scopes
// ============================================

test('scope active filtra pedidos activos correctamente', function () {
    createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ACTIVE-1',
        'status' => OrderStatus::CONFIRMED,
    ]);

    createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'CLOSED-1',
        'status' => OrderStatus::CLOSED,
    ]);

    createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'CANCELLED-1',
        'status' => OrderStatus::CANCELLED,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Test',
    ]);

    expect(Order::active()->count())->toBe(1);
    expect(Order::active()->first()->order_number)->toBe('ACTIVE-1');
});

test('scope inKitchenQueue filtra cola de cocina', function () {
    createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'CONF-1',
        'status' => OrderStatus::CONFIRMED,
    ]);

    createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'PREP-1',
        'status' => OrderStatus::PREPARING,
    ]);

    createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'READY-1',
        'status' => OrderStatus::READY,
    ]);

    expect(Order::inKitchenQueue()->count())->toBe(2);
});

test('scope awaitingPayment filtra pedidos esperando pago', function () {
    createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'SERVED-1',
        'status' => OrderStatus::SERVED,
    ]);

    createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'PAID-1',
        'status' => OrderStatus::PAID,
    ]);

    expect(Order::awaitingPayment()->count())->toBe(1);
});

// ============================================
// OrderItem cálculo automático
// ============================================

test('OrderItem calcula subtotal automaticamente al guardar', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
    ]);

    $item = OrderItem::create([
        'company_id' => $this->company->id,
        'order_id' => $order->id,
        'name_snapshot' => 'Hamburguesa Clásica',
        'unit_price_snapshot' => 5990,
        'quantity' => 2,
    ]);

    expect($item->subtotal)->toEqual(11980);
});

test('Order recalculateTotals calcula subtotal IVA y total', function () {
    $order = createOrder([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
    ]);

    OrderItem::create([
        'company_id' => $this->company->id,
        'order_id' => $order->id,
        'name_snapshot' => 'Hamburguesa',
        'unit_price_snapshot' => 10000,
        'quantity' => 1,
    ]);

    $order->recalculateTotals();
    $order->save();

    expect($order->subtotal)->toEqual(10000);
    expect($order->tax_amount)->toEqual(1900); // 19% IVA
    expect($order->total)->toEqual(11900);
});
