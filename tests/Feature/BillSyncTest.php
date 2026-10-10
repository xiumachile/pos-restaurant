<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Payments\Domain\Entities\Bill;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'SYNC-' . uniqid(),
        'legal_name' => 'Bill Sync Test',
        'trade_name' => 'Bill Sync',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'SYNC',
        'name' => 'Bill Sync Branch',
    ]);

    $this->user = User::create([
        'name' => 'Cashier Sync',
        'email' => 'sync-' . uniqid() . '@test.com',
        'password' => bcrypt('password'),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);
});

test('POST /api/v1/bills crea bill desde sync offline (el servidor recalcula montos)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SYNC-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
        'amount_due' => 11900,
    ]);

    // El cliente envía campos client_* como diagnóstico, pero el servidor ignora estos montos
    // y calcula basándose en el order real.
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/bills', [
            'order_uuid' => $order->uuid,
            'type' => 'single',
            'client_total' => 99999, // Manipulado, el servidor lo ignorará
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'open',
            'idempotent' => false,
            'total' => 11900, // El total REAL del order, no el manipulado
        ]);

    $this->assertDatabaseHas('bills', [
        'company_id' => $this->company->id,
        'total' => 11900,
        'remaining_amount' => 11900,
    ]);
});

test('POST /api/v1/bills es idempotente vía idempotency_key', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SYNC-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
        'amount_due' => 11900,
    ]);

    $idempotencyKey = Str::uuid()->toString();

    $payload = [
        'order_uuid' => $order->uuid,
        'type' => 'single',
        'idempotency_key' => $idempotencyKey,
    ];

    // Primera petición: crea bill
    $response1 = $this->actingAs($this->user)
        ->postJson('/api/v1/bills', $payload);
    $response1->assertStatus(201)
        ->assertJson(['idempotent' => false]);

    $uuid1 = $response1->json('uuid');

    // Segunda petición con mismo idempotency_key: retorna bill existente
    $response2 = $this->actingAs($this->user)
        ->postJson('/api/v1/bills', $payload);
    $response2->assertStatus(200)
        ->assertJson([
            'uuid' => $uuid1,
            'idempotent' => true,
        ]);

    // Verificar que solo existe una bill
    $this->assertDatabaseCount('bills', 1);
});

test('POST /api/v1/bills valida que order pertenece al tenant', function () {
    $otherCompany = Company::create([
        'tax_id' => 'OTHER-' . uniqid(),
        'legal_name' => 'Other Company',
        'trade_name' => 'Other',
    ]);

    $otherBranch = Branch::create([
        'company_id' => $otherCompany->id,
        'code' => 'OTHER',
        'name' => 'Other Branch',
    ]);

    $otherOrder = Order::create([
        'company_id' => $otherCompany->id,
        'branch_id' => $otherBranch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-OTHER-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
        'amount_due' => 11900,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/bills', [
            'order_uuid' => $otherOrder->uuid, // Order de otra empresa
            'type' => 'single',
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('order_uuid');
});

test('POST /api/v1/bills soporta split bills (equal_split) desde sync', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SYNC-004',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
        'amount_due' => 11900,
    ]);

    // Sincronizar intención de división en 2 partes
    $response1 = $this->actingAs($this->user)
        ->postJson('/api/v1/bills', [
            'order_uuid' => $order->uuid,
            'type' => 'equal_split',
            'parts' => 2,
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    // El servidor crea la primera bill del split (o ambas, dependiendo de la lógica, 
    // pero aquí tomamos la respuesta de la primera o verificamos que se crearon)
    $response1->assertStatus(201);

    // Verificar que se crearon bills para este order
    $this->assertDatabaseHas('bills', [
        'order_id' => $order->id,
        'type' => 'equal_split',
    ]);
});

test('POST /api/v1/bills requiere autenticación', function () {
    $response = $this->postJson('/api/v1/bills', [
        'order_uuid' => Str::uuid()->toString(),
        'type' => 'single',
        'idempotency_key' => Str::uuid()->toString(),
    ]);

    $response->assertStatus(401);
});

test('POST /api/v1/bills valida tipos de campos (ADR-018)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SYNC-005',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
        'amount_due' => 11900,
    ]);

    // Enviar parts como string en lugar de integer
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/bills', [
            'order_uuid' => $order->uuid,
            'type' => 'equal_split',
            'parts' => 'dos', // Debería ser integer
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('parts');
});
