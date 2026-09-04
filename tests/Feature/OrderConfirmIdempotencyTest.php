<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

/**
 * Tests de idempotencia para POST /orders/{uuid}/confirm
 * 
 * Valida que el endpoint de confirmación es idempotente a nivel de dominio:
 * - Confirmar un pedido ya confirmado retorna 200 sin disparar eventos
 * - Intentar confirmar pedido en estado inválido retorna 422
 * - Reintentos con mismo idempotency key retornan respuesta cacheada
 */
beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'IDEM-' . uniqid(),
        'legal_name' => 'Idempotency Test Company',
        'trade_name' => 'Idempotency Test Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'IDEM',
        'name' => 'Idempotency Test Branch',
    ]);

    $this->user = User::create([
        'name' => 'Idempotency User',
        'email' => 'idempotency-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'admin',
    ]);

    $this->table = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'area_code' => 'IDEM-AREA',
        'area_name_translations' => ['es' => 'Área Idempotencia', 'zh' => '幂等区'],
        'table_number' => 'T-IDEM-' . uniqid(),
        'capacity' => 4,
        'status' => 'available',
    ]);

    $this->token = JWTAuth::fromUser($this->user);
});

function idempotencyHeaders(string $token, ?string $idempotencyKey = null): array
{
    $headers = [
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];

    if ($idempotencyKey) {
        $headers['Idempotency-Key'] = $idempotencyKey;
    }

    return $headers;
}

/**
 * Helper para crear un order de prueba con todos los campos requeridos
 */
function idempotencyCreateTestOrder($company, $branch, $table, $user, $status = OrderStatus::DRAFT): Order
{
    return Order::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'table_id' => $table->id,
        'user_id' => $user->id,
        'order_number' => 'TEST-' . uniqid(),
        'type' => 'dine_in',
        'status' => $status,
        'fulfillment_channel' => 'onsite',
    ]);
}

// ============================================
// TEST 1: Confirmación exitosa (primera vez)
// ============================================
it('confirma un pedido draft exitosamente', function () {
    $order = idempotencyCreateTestOrder($this->company, $this->branch, $this->table, $this->user, OrderStatus::DRAFT);

    $response = $this->withHeaders(idempotencyHeaders($this->token, uuid_create()))
        ->postJson("/api/v1/orders/{$order->uuid}/confirm");

    $response->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    $order->refresh();
    expect($order->status->value)->toBe('confirmed');
    expect($order->confirmed_at)->not->toBeNull();
});

// ============================================
// TEST 2: Idempotencia de dominio - confirmar pedido YA confirmado
// ============================================
it('retorna 200 cuando el pedido ya está confirmado sin disparar eventos', function () {
    $order = idempotencyCreateTestOrder($this->company, $this->branch, $this->table, $this->user, OrderStatus::CONFIRMED);
    $order->confirmed_at = now();
    $order->save();

    \Illuminate\Support\Facades\Event::fake([\Modules\Orders\Domain\Events\OrderConfirmed::class]);

    $response = $this->withHeaders(idempotencyHeaders($this->token, uuid_create()))
        ->postJson("/api/v1/orders/{$order->uuid}/confirm");

    // CRÍTICO: debe retornar 200 (idempotencia de dominio)
    $response->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    // CRÍTICO: NO debe disparar eventos de nuevo
    \Illuminate\Support\Facades\Event::assertNotDispatched(\Modules\Orders\Domain\Events\OrderConfirmed::class);
});

// ============================================
// TEST 3: Intentar confirmar pedido PAID → 422
// ============================================
it('retorna 422 al intentar confirmar un pedido ya pagado', function () {
    $order = idempotencyCreateTestOrder($this->company, $this->branch, $this->table, $this->user, OrderStatus::PAID);
    $order->paid_at = now();
    $order->save();

    $response = $this->withHeaders(idempotencyHeaders($this->token, uuid_create()))
        ->postJson("/api/v1/orders/{$order->uuid}/confirm");

    $response->assertStatus(422)
        ->assertJsonPath('error', 'invalid_transition');
});

// ============================================
// TEST 4: Intentar confirmar pedido CANCELLED → 422
// ============================================
it('retorna 422 al intentar confirmar un pedido cancelado', function () {
    $order = idempotencyCreateTestOrder($this->company, $this->branch, $this->table, $this->user, OrderStatus::CANCELLED);
    $order->cancellation_reason = 'Test cancellation';
    $order->cancelled_at = now();
    $order->save();

    $response = $this->withHeaders(idempotencyHeaders($this->token, uuid_create()))
        ->postJson("/api/v1/orders/{$order->uuid}/confirm");

    $response->assertStatus(422)
        ->assertJsonPath('error', 'invalid_transition');
});

// ============================================
// TEST 5: Múltiples reintentos con MISMO idempotency key
// ============================================
it('maneja múltiples reintentos con la misma idempotency key', function () {
    $order = idempotencyCreateTestOrder($this->company, $this->branch, $this->table, $this->user, OrderStatus::DRAFT);

    $idempotencyKey = uuid_create();

    // Primera llamada
    $response1 = $this->withHeaders(idempotencyHeaders($this->token, $idempotencyKey))
        ->postJson("/api/v1/orders/{$order->uuid}/confirm");

    $response1->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    // Segunda llamada con MISMO idempotency key (middleware cachea respuesta)
    $response2 = $this->withHeaders(idempotencyHeaders($this->token, $idempotencyKey))
        ->postJson("/api/v1/orders/{$order->uuid}/confirm");

    $response2->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    // Tercera llamada con NUEVO idempotency key
    // Debe funcionar por idempotencia de dominio (pedido ya confirmado)
    $response3 = $this->withHeaders(idempotencyHeaders($this->token, uuid_create()))
        ->postJson("/api/v1/orders/{$order->uuid}/confirm");

    $response3->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    $order->refresh();
    expect($order->status->value)->toBe('confirmed');
});
