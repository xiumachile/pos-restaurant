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

test('POST /api/v1/bills crea bill desde sync offline', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SYNC-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/bills', [
            'order_uuid' => $order->uuid,
            'bill_number' => 'BILL-001',
            'type' => 'single',
            'subtotal' => 10000,
            'tax_amount' => 1900,
            'discount_amount' => 0,
            'tip_amount' => 500,
            'total' => 12400,
            'paid_amount' => 0,
            'remaining_amount' => 12400,
            'status' => 'open',
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'bill_number' => 'BILL-001',
            'status' => 'open',
            'idempotent' => false,
        ]);

    $this->assertDatabaseHas('bills', [
        'bill_number' => 'BILL-001',
        'company_id' => $this->company->id,
        'subtotal' => 10000,
        'total' => 12400,
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
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $idempotencyKey = Str::uuid()->toString();

    $payload = [
        'order_uuid' => $order->uuid,
        'bill_number' => 'BILL-002',
        'type' => 'single',
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'tip_amount' => 500,
        'total' => 12400,
        'paid_amount' => 0,
        'remaining_amount' => 12400,
        'status' => 'open',
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

test('POST /api/v1/bills valida invariante paid + remaining = total', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SYNC-003',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/bills', [
            'order_uuid' => $order->uuid,
            'bill_number' => 'BILL-003',
            'type' => 'single',
            'subtotal' => 10000,
            'tax_amount' => 1900,
            'discount_amount' => 0,
            'tip_amount' => 500,
            'total' => 12400,
            'paid_amount' => 5000,
            'remaining_amount' => 8000, // 5000 + 8000 = 13000 != 12400
            'status' => 'partial',
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('remaining_amount');
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

    $otherUser = User::create([
        'name' => 'Other Cashier',
        'email' => 'other-' . uniqid() . '@test.com',
        'password' => bcrypt('password'),
        'company_id' => $otherCompany->id,
        'branch_id' => $otherBranch->id,
        'role' => 'cashier',
    ]);

    $otherOrder = Order::create([
        'company_id' => $otherCompany->id,
        'branch_id' => $otherBranch->id,
        'waiter_id' => $otherUser->id,
        'order_number' => 'ORD-OTHER-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/bills', [
            'order_uuid' => $otherOrder->uuid, // Order de otra empresa
            'bill_number' => 'BILL-004',
            'type' => 'single',
            'subtotal' => 10000,
            'tax_amount' => 1900,
            'discount_amount' => 0,
            'tip_amount' => 500,
            'total' => 12400,
            'paid_amount' => 0,
            'remaining_amount' => 12400,
            'status' => 'open',
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('order_uuid');
});

test('POST /api/v1/bills soporta split bills (equal_split)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-SYNC-004',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    // Crear primera bill del split
    $response1 = $this->actingAs($this->user)
        ->postJson('/api/v1/bills', [
            'order_uuid' => $order->uuid,
            'bill_number' => 'BILL-SPLIT-001-A',
            'type' => 'equal_split',
            'subtotal' => 5000,
            'tax_amount' => 950,
            'discount_amount' => 0,
            'tip_amount' => 250,
            'total' => 6200,
            'paid_amount' => 0,
            'remaining_amount' => 6200,
            'status' => 'open',
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    $response1->assertStatus(201);

    // Crear segunda bill del split
    $response2 = $this->actingAs($this->user)
        ->postJson('/api/v1/bills', [
            'order_uuid' => $order->uuid,
            'bill_number' => 'BILL-SPLIT-001-B',
            'type' => 'equal_split',
            'subtotal' => 5000,
            'tax_amount' => 950,
            'discount_amount' => 0,
            'tip_amount' => 250,
            'total' => 6200,
            'paid_amount' => 0,
            'remaining_amount' => 6200,
            'status' => 'open',
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    $response2->assertStatus(201);

    // Verificar que ambas bills existen
    $this->assertDatabaseCount('bills', 2);
    $this->assertDatabaseHas('bills', ['bill_number' => 'BILL-SPLIT-001-A']);
    $this->assertDatabaseHas('bills', ['bill_number' => 'BILL-SPLIT-001-B']);
});

test('POST /api/v1/bills requiere autenticación', function () {
    $response = $this->postJson('/api/v1/bills', [
        'order_uuid' => Str::uuid()->toString(),
        'bill_number' => 'BILL-005',
        'type' => 'single',
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'tip_amount' => 500,
        'total' => 12400,
        'paid_amount' => 0,
        'remaining_amount' => 12400,
        'status' => 'open',
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
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    // Enviar decimales en campos que deben ser integer
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/bills', [
            'order_uuid' => $order->uuid,
            'bill_number' => 'BILL-006',
            'type' => 'single',
            'subtotal' => 10000.50, // Debería ser integer
            'tax_amount' => 1900,
            'discount_amount' => 0,
            'tip_amount' => 500,
            'total' => 12400,
            'paid_amount' => 0,
            'remaining_amount' => 12400,
            'status' => 'open',
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('subtotal');
});
