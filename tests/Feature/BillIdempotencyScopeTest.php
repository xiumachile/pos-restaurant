<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Payments\Domain\Entities\Bill;
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

    $this->order1 = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-SCOPE-001',
        'type' => 'dine_in',
        'status' => 'served',
        'waiter_id' => $this->user->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
        'amount_due' => 11900,
    ]);

    $this->order2 = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-SCOPE-002',
        'type' => 'dine_in',
        'status' => 'served',
        'waiter_id' => $this->user->id,
        'subtotal' => 5000,
        'tax_amount' => 950,
        'total' => 5950,
        'amount_due' => 5950,
    ]);

    $this->token = JWTAuth::fromUser($this->user);
});

test('CA-03: Reutilizar idempotency_key con el mismo pedido y payload retorna la bill existente (idempotencia)', function () {
    $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();
    
    // Primera petición
    $response1 = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
        ->postJson('/api/v1/bills', [
            'order_uuid' => $this->order1->uuid,
            'idempotency_key' => $idempotencyKey,
            'type' => 'single',
        ]);

    $response1->assertStatus(201)
        ->assertJsonFragment(['idempotent' => false]);
    
    $uuid1 = $response1->json('uuid');

    // Segunda petición con los mismos datos
    $response2 = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
        ->postJson('/api/v1/bills', [
            'order_uuid' => $this->order1->uuid,
            'idempotency_key' => $idempotencyKey,
            'type' => 'single',
        ]);

    $response2->assertStatus(200)
        ->assertJsonFragment(['idempotent' => true])
        ->assertJsonFragment(['uuid' => $uuid1]);

    expect(Bill::where('idempotency_key', $idempotencyKey)->count())->toBe(1);
});

test('CA-03: Reutilizar idempotency_key con un pedido diferente retorna conflicto 409', function () {
    $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();
    
    // Crear bill para order1
    $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
        ->postJson('/api/v1/bills', [
            'order_uuid' => $this->order1->uuid,
            'idempotency_key' => $idempotencyKey,
            'type' => 'single',
        ])->assertStatus(201);

    // Intentar reutilizar la misma clave para order2
    $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
        ->postJson('/api/v1/bills', [
            'order_uuid' => $this->order2->uuid,
            'idempotency_key' => $idempotencyKey,
            'type' => 'single',
        ]);

    $response->assertStatus(409)
        ->assertJsonFragment(['error' => 'idempotency_key_conflict']);

    expect(Bill::where('idempotency_key', $idempotencyKey)->count())->toBe(1);
});

test('CA-03: Reutilizar idempotency_key con el mismo pedido pero payload diferente retorna conflicto 409', function () {
    $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();
    
    // Crear bill single para order1
    $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
        ->postJson('/api/v1/bills', [
            'order_uuid' => $this->order1->uuid,
            'idempotency_key' => $idempotencyKey,
            'type' => 'single',
        ])->assertStatus(201);

    // Intentar reutilizar la misma clave pero con tipo 'equal_split' (payload diferente)
    $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
        ->postJson('/api/v1/bills', [
            'order_uuid' => $this->order1->uuid,
            'idempotency_key' => $idempotencyKey,
            'type' => 'equal_split',
            'parts' => 2,
        ]);

    $response->assertStatus(409)
        ->assertJsonFragment(['error' => 'idempotency_payload_conflict']);

    expect(Bill::where('idempotency_key', $idempotencyKey)->count())->toBe(1);
});

test('CA-03: Crear bill legítima con nueva idempotency_key funciona correctamente', function () {
    $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();
    
    $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
        ->postJson('/api/v1/bills', [
            'order_uuid' => $this->order1->uuid,
            'idempotency_key' => $idempotencyKey,
            'type' => 'single',
        ]);

    $response->assertStatus(201)
        ->assertJsonFragment(['idempotent' => false])
        ->assertJsonFragment(['total' => 11900]);

    $bill = Bill::where('idempotency_key', $idempotencyKey)->first();
    expect($bill)->not->toBeNull()
        ->and($bill->payload_hash)->not->toBeNull()
        ->and(strlen($bill->payload_hash))->toBe(64);
});
