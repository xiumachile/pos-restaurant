<?php

use Modules\Audit\Domain\Entities\AuditLog;
use Modules\Audit\Domain\Services\AuditService;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::forceCreate([
        'tax_id' => '76.111.000-1',
        'legal_name' => 'Audit Test SpA',
        'trade_name' => 'Audit Test',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'AUDIT',
        'name' => 'Audit Branch',
    ]);

    $this->user = User::forceCreate([
        'name' => 'Audit User',
        'email' => 'audit-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);

    $this->auditService = new AuditService();
});

test('AuditLog se puede crear con datos válidos', function () {
    $log = AuditLog::create([
        'uuid' => \Illuminate\Support\Str::uuid(),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->user->id,
        'user_name' => $this->user->name,
        'action' => 'order_cancelled',
        'entity_type' => Order::class,
        'entity_id' => 1,
        'payload' => ['order_number' => 'ORD-001'],
        'reason' => 'Cliente cambió de opinión',
        'occurred_at' => now(),
    ]);

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('order_cancelled')
        ->and($log->payload['order_number'])->toBe('ORD-001');
});

test('AuditLog es inmutable: no se puede actualizar', function () {
    $log = AuditLog::create([
        'uuid' => \Illuminate\Support\Str::uuid(),
        'action' => 'test_action',
        'entity_type' => Order::class,
        'entity_id' => 1,
        'occurred_at' => now(),
    ]);

    expect(fn() => $log->update(['reason' => 'changed']))
        ->toThrow(RuntimeException::class, 'AuditLog es inmutable');
});

test('AuditLog es inmutable: no se puede eliminar', function () {
    $log = AuditLog::create([
        'uuid' => \Illuminate\Support\Str::uuid(),
        'action' => 'test_action',
        'entity_type' => Order::class,
        'entity_id' => 1,
        'occurred_at' => now(),
    ]);

    expect(fn() => $log->delete())
        ->toThrow(RuntimeException::class, 'AuditLog es inmutable');
});

test('AuditService registra eventos correctamente', function () {
    $this->actingAs($this->user);

    $log = $this->auditService->log(
        action: 'test_event',
        entityType: Order::class,
        entityId: 123,
        entityUuid: \Illuminate\Support\Str::uuid(),
        payload: ['key' => 'value'],
        reason: 'Test reason'
    );

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('test_event')
        ->and($log->user_id)->toBe($this->user->id)
        ->and($log->reason)->toBe('Test reason');
});

test('AuditService registra cancelación de orden', function () {
    $this->actingAs($this->user);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-AUDIT-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CANCELLED,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    $log = $this->auditService->logOrderCancellation($order, 'Cliente canceló');

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('order_cancelled')
        ->and($log->entity_type)->toBe(Order::class)
        ->and($log->entity_id)->toBe($order->id)
        ->and($log->payload['order_number'])->toBe('ORD-AUDIT-001')
        ->and($log->reason)->toBe('Cliente canceló');
});

test('AuditService registra descuento aplicado', function () {
    $this->actingAs($this->user);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-AUDIT-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 100,
        'total' => 1090,
    ]);

    $log = $this->auditService->logDiscountApplied($order, 100.0, 'Descuento cortesía');

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('discount_applied')
        ->and((float) $log->payload['discount_amount'])->toEqual(100.0)
        ->and($log->reason)->toBe('Descuento cortesía');
});

test('AuditService registra cambio de precio', function () {
    $this->actingAs($this->user);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-AUDIT-003',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    $log = $this->auditService->logPriceChanged($order, 1000.0, 1200.0, 'Ajuste de precio');

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('price_changed')
        ->and((float) $log->changes['price']['before'])->toEqual(1000.0)
        ->and((float) $log->changes['price']['after'])->toEqual(1200.0);
});

test('Scope action filtra correctamente', function () {
    AuditLog::create([
        'uuid' => \Illuminate\Support\Str::uuid(),
        'action' => 'order_cancelled',
        'entity_type' => Order::class,
        'entity_id' => 1,
        'occurred_at' => now(),
    ]);

    AuditLog::create([
        'uuid' => \Illuminate\Support\Str::uuid(),
        'action' => 'discount_applied',
        'entity_type' => Order::class,
        'entity_id' => 2,
        'occurred_at' => now(),
    ]);

    expect(AuditLog::action('order_cancelled')->count())->toBe(1);
    expect(AuditLog::action('discount_applied')->count())->toBe(1);
    expect(AuditLog::action('nonexistent')->count())->toBe(0);
});

test('Scope byUser filtra correctamente', function () {
    AuditLog::create([
        'uuid' => \Illuminate\Support\Str::uuid(),
        'user_id' => $this->user->id,
        'action' => 'test',
        'entity_type' => Order::class,
        'entity_id' => 1,
        'occurred_at' => now(),
    ]);

    AuditLog::create([
        'uuid' => \Illuminate\Support\Str::uuid(),
        'user_id' => null,
        'action' => 'test',
        'entity_type' => Order::class,
        'entity_id' => 2,
        'occurred_at' => now(),
    ]);

    expect(AuditLog::byUser($this->user->id)->count())->toBe(1);
});
