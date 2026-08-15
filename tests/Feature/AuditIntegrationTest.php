<?php

use Modules\Audit\Domain\Entities\AuditLog;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Services\OrderStateMachine;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Payments\Domain\Services\CashSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::forceCreate([
        'tax_id' => '76.999.888-7',
        'legal_name' => 'Audit Integration SpA',
        'trade_name' => 'Audit Integration',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'AUDINT',
        'name' => 'Audit Integration Branch',
    ]);

    $this->manager = User::forceCreate([
        'name' => 'Manager User',
        'email' => 'manager-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);
});

test('Cancelar pedido crea registro de auditoría automáticamente', function () {
    $this->actingAs($this->manager);

    // Crear orden en estado confirmable
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->manager->id,
        'order_number' => 'ORD-AUDIT-CANCEL',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    // Verificar que NO existe audit log aún
    expect(AuditLog::where('entity_id', $order->id)->count())->toBe(0);

    // Cancelar usando el state machine (flujo REAL)
    $stateMachine = app(OrderStateMachine::class);
    $stateMachine->transition($order, OrderStatus::CANCELLED, 'Cliente se fue');

    // Verificar que se creó el audit log AUTOMÁTICAMENTE
    $auditLog = AuditLog::where('entity_id', $order->id)
        ->where('action', 'order_cancelled')
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->entity_type)->toBe(Order::class)
        ->and($auditLog->reason)->toBe('Cliente se fue')
        ->and($auditLog->user_id)->toBe($this->manager->id)
        ->and($auditLog->payload['order_number'])->toBe('ORD-AUDIT-CANCEL');
});

test('Aplicar descuento crea registro de auditoría automáticamente', function () {
    $this->actingAs($this->manager);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->manager->id,
        'order_number' => 'ORD-AUDIT-DISCOUNT',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
    ]);

    // Aplicar descuento usando el state machine (flujo REAL)
    $stateMachine = app(OrderStateMachine::class);
    $stateMachine->applyDiscount($order, 500.0, 'Descuento cortesía');

    // Verificar que se creó el audit log AUTOMÁTICAMENTE
    $auditLog = AuditLog::where('entity_id', $order->id)
        ->where('action', 'discount_applied')
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->entity_type)->toBe(Order::class)
        ->and($auditLog->reason)->toBe('Descuento cortesía')
        ->and((float) $auditLog->payload['discount_amount'])->toEqual(500.0);
});

test('Abrir sesión de caja crea registro de auditoría automáticamente', function () {
    $this->actingAs($this->manager);

    // Verificar que NO existe audit log aún
    expect(AuditLog::where('action', 'drawer_opened')->count())->toBe(0);

    // Abrir sesión usando el servicio (flujo REAL)
    $service = app(CashSessionService::class);
    $session = $service->openSession(
        companyId: $this->company->id,
        branchId: $this->branch->id,
        userId: $this->manager->id,
        openingAmount: 50000.0,
        notes: 'Apertura matutina'
    );

    // Verificar que se creó el audit log AUTOMÁTICAMENTE
    $auditLog = AuditLog::where('action', 'drawer_opened')
        ->where('entity_id', $session->id)
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->payload['session_number'])->toBe($session->session_number)
        ->and($auditLog->reason)->toBe('Apertura matutina')
        ->and($auditLog->user_id)->toBe($this->manager->id);
});

test('API GET /audit-logs retorna eventos creados por wiring automático', function () {
    $this->actingAs($this->manager);

    // Crear orden y cancelarla (flujo real)
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->manager->id,
        'order_number' => 'ORD-AUDIT-API',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    $stateMachine = app(OrderStateMachine::class);
    $stateMachine->transition($order, OrderStatus::CANCELLED, 'Test API');

    // Consultar vía API
    $response = $this->actingAs($this->manager, 'api')
        ->getJson('/api/v1/audit-logs?action=order_cancelled');

    $response->assertOk()
        ->assertJsonPath('success', true);

    $data = $response->json('data.data');
    expect($data)->not->toBeEmpty();
    
    // Verificar que el registro creado por wiring está en la respuesta
    $found = collect($data)->firstWhere('entity_id', $order->id);
    expect($found)->not->toBeNull()
        ->and($found['action'])->toBe('order_cancelled')
        ->and($found['reason'])->toBe('Test API');
});
