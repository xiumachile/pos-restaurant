<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\ValueObjects\BillType;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

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

    $this->order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-SYNC-001',
        'type' => 'dine_in',
        'status' => 'served',
        'waiter_id' => $this->user->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
        'amount_due' => 11900,
    ]);

    $this->item1 = OrderItem::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $this->order->id,
        'name_snapshot' => 'Hamburguesa',
        'quantity' => 1,
        'unit_price_snapshot' => 10000,
        'subtotal' => 10000,
        'tax_amount' => 1900,
    ]);

    $this->token = JWTAuth::fromUser($this->user);
});

test('CA-02: Sincronización de bill con montos manipulados por el cliente es ignorada y recalculada por el servidor', function () {
    $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();
    
    $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
        ->postJson('/api/v1/bills', [
            'order_uuid' => $this->order->uuid,
            'idempotency_key' => $idempotencyKey,
            'type' => 'single',
            'client_subtotal' => 10000,
            'client_total' => 1000000,       // Manipulado
            'client_paid_amount' => 1000000, // Manipulado
        ]);

    $response->assertStatus(201)
        ->assertJsonFragment(['idempotent' => false])
        ->assertJsonFragment(['total' => 11900])
        ->assertJsonFragment(['remaining_amount' => 11900]);

    $bill = Bill::where('idempotency_key', $idempotencyKey)->first();
    expect($bill)->not->toBeNull()
        ->and($bill->total)->toBe(11900)
        ->and($bill->remaining_amount)->toBe(11900)
        ->and($bill->paid_amount)->toBe(0);
});

test('CA-02: Sincronización de bill by_items con items que no pertenecen al order es rechazada', function () {
    $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();
    $fakeItemUuid = \Illuminate\Support\Str::uuid()->toString();
    
    $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
        ->postJson('/api/v1/bills', [
            'order_uuid' => $this->order->uuid,
            'idempotency_key' => $idempotencyKey,
            'type' => 'by_items',
            'item_uuids' => [$fakeItemUuid],
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'Uno o más items no pertenecen a este pedido: ' . $fakeItemUuid]);

    $bill = Bill::where('idempotency_key', $idempotencyKey)->first();
    expect($bill)->toBeNull();
});

test('CA-02: Sincronización de bill custom_amount con monto que excede el total del order es rechazada', function () {
    $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();
    
    $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
        ->postJson('/api/v1/bills', [
            'order_uuid' => $this->order->uuid,
            'idempotency_key' => $idempotencyKey,
            'type' => 'custom_amount',
            'client_amount' => 50000,
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['error' => 'payment_exception']);

    $bill = Bill::where('idempotency_key', $idempotencyKey)->first();
    expect($bill)->toBeNull();
});

test('CA-02: Sincronización legítima de bill single crea la bill con montos correctos del servidor', function () {
    $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();
    
    $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
        ->postJson('/api/v1/bills', [
            'order_uuid' => $this->order->uuid,
            'idempotency_key' => $idempotencyKey,
            'type' => 'single',
        ]);

    $response->assertStatus(201)
        ->assertJsonFragment(['idempotent' => false])
        ->assertJsonFragment(['total' => 11900])
        ->assertJsonFragment(['remaining_amount' => 11900]);

    $bill = Bill::where('idempotency_key', $idempotencyKey)->first();
    expect($bill)->not->toBeNull()
        ->and($bill->total)->toBe(11900)
        ->and($bill->type->value)->toBe('single')
        ->and($bill->idempotency_key)->toBe($idempotencyKey);
});
