<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\FulfillmentChannel;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Tables\Domain\Entities\RestaurantTable;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

/**
 * Tests de Fase 4 — transiciones condicionales por canal de fulfillment.
 *
 * Valida:
 * 1. Flujo completo pickup (takeout)
 * 2. Flujo completo delivery
 * 3. Transiciones inválidas por canal
 * 4. Backward compatibility (legacy READY → SERVED sigue válido)
 */
beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'OFT-' . uniqid(),
        'legal_name' => 'Order Fulfillment Transitions Test',
        'trade_name' => 'OFT Restaurant',
    ]);

    enableAllCapabilities($this->company);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'OFT',
        'name' => 'OFT Branch',
    ]);

    $this->table = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'table_number' => '1',
        'capacity' => 4,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón'],
    ]);

    // Usar admin para tests de transición (los tests validan lógica de estados,
    // no autorización de rol — eso se prueba en OrderPolicyTest separado)
    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin-oft-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'admin',
    ]);

    $this->token = JWTAuth::fromUser($this->admin);
});

function oftHeaders(string $token): array
{
    return [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

function createOrderViaApi($test, array $payload): string
{
    $response = $test->withHeaders(oftHeaders($test->token))
        ->postJson('/api/v1/orders', $payload);
    return $response->json('data.uuid');
}

// ============================================
// FLUJO COMPLETO PICKUP (takeout)
// ============================================

test('flujo completo pickup: draft → confirmed → preparing → ready → ready_for_pickup → picked_up → paid', function () {
    $uuid = createOrderViaApi($this, ['type' => 'takeout']);
    $headers = oftHeaders($this->token);

    // draft → confirmed
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/confirm")
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    // confirmed → preparing
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/prepare")
        ->assertOk()
        ->assertJsonPath('data.status', 'preparing');

    // preparing → ready
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/ready")
        ->assertOk()
        ->assertJsonPath('data.status', 'ready');

    // ready → ready_for_pickup (específico de pickup)
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/ready-for-pickup")
        ->assertOk()
        ->assertJsonPath('data.status', 'ready_for_pickup');

    // ready_for_pickup → picked_up
    $response = $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/pickup");
    $response->assertOk()
        ->assertJsonPath('data.status', 'picked_up')
        ->assertJsonPath('data.picked_up_at', fn($v) => !is_null($v));

    // picked_up → paid
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/pay")
        ->assertOk()
        ->assertJsonPath('data.status', 'paid');
});

// ============================================
// FLUJO COMPLETO DELIVERY
// ============================================

test('flujo completo delivery: draft → confirmed → preparing → ready → dispatched → delivered → paid', function () {
    $uuid = createOrderViaApi($this, [
        'type' => 'delivery',
        'customer_name' => 'Juan Pérez',
        'customer_phone' => '+56912345678',
        'delivery_address' => 'Av. Providencia 1234',
    ]);
    $headers = oftHeaders($this->token);

    // draft → confirmed → preparing → ready
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/confirm")
        ->assertJsonPath('data.status', 'confirmed');
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/prepare")
        ->assertJsonPath('data.status', 'preparing');
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/ready")
        ->assertJsonPath('data.status', 'ready');

    // ready → dispatched (específico de delivery)
    $response = $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/dispatch");
    $response->assertOk()
        ->assertJsonPath('data.status', 'dispatched')
        ->assertJsonPath('data.dispatched_at', fn($v) => !is_null($v));

    // dispatched → delivered
    $response = $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/deliver");
    $response->assertOk()
        ->assertJsonPath('data.status', 'delivered')
        ->assertJsonPath('data.delivered_at', fn($v) => !is_null($v));

    // delivered → paid
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/pay")
        ->assertJsonPath('data.status', 'paid');
});

// ============================================
// TRANSICIONES INVÁLIDAS POR CANAL
// ============================================

test('pedido pickup NO puede saltar a dispatched (es de delivery)', function () {
    $uuid = createOrderViaApi($this, ['type' => 'takeout']);
    $headers = oftHeaders($this->token);

    // Llevar hasta ready
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/confirm");
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/prepare");
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/ready");

    // Intentar dispatch (inválido para pickup)
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/dispatch")
        ->assertStatus(422)
        ->assertJsonPath('error', 'invalid_transition');
});

test('pedido delivery NO puede saltar a ready_for_pickup (es de pickup)', function () {
    $uuid = createOrderViaApi($this, [
        'type' => 'delivery',
        'customer_name' => 'Juan Pérez',
        'customer_phone' => '+56912345678',
        'delivery_address' => 'Av. Providencia 1234',
    ]);
    $headers = oftHeaders($this->token);

    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/confirm");
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/prepare");
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/ready");

    // Intentar ready-for-pickup (inválido para delivery)
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/ready-for-pickup")
        ->assertStatus(422)
        ->assertJsonPath('error', 'invalid_transition');
});

test('pedido onsite NO puede saltar a dispatched ni ready_for_pickup', function () {
    $uuid = createOrderViaApi($this, [
        'type' => 'dine_in',
        'table_uuid' => $this->table->uuid,
    ]);
    $headers = oftHeaders($this->token);

    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/confirm");
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/prepare");
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/ready");

    // Dispatch inválido
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/dispatch")
        ->assertStatus(422);

    // Ready-for-pickup inválido
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/ready-for-pickup")
        ->assertStatus(422);
});

// ============================================
// BACKWARD COMPATIBILITY (legacy READY → SERVED)
// ============================================

test('legacy: pickup permite READY → SERVED para compatibilidad', function () {
    $uuid = createOrderViaApi($this, ['type' => 'takeout']);
    $headers = oftHeaders($this->token);

    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/confirm");
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/prepare");
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/ready");

    // Legacy path: ready → served (debe seguir funcionando)
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/serve")
        ->assertOk()
        ->assertJsonPath('data.status', 'served');
});

test('legacy: delivery permite READY → SERVED para compatibilidad', function () {
    $uuid = createOrderViaApi($this, [
        'type' => 'delivery',
        'customer_name' => 'Juan Pérez',
        'customer_phone' => '+56912345678',
        'delivery_address' => 'Av. Providencia 1234',
    ]);
    $headers = oftHeaders($this->token);

    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/confirm");
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/prepare");
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/ready");

    // Legacy path: ready → served
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/serve")
        ->assertOk()
        ->assertJsonPath('data.status', 'served');
});

// ============================================
// OrderStatus enum — tests unitarios
// ============================================

test('OrderStatus tiene 4 nuevos casos de fulfillment', function () {
    expect(OrderStatus::READY_FOR_PICKUP->value)->toBe('ready_for_pickup')
        ->and(OrderStatus::PICKED_UP->value)->toBe('picked_up')
        ->and(OrderStatus::DISPATCHED->value)->toBe('dispatched')
        ->and(OrderStatus::DELIVERED->value)->toBe('delivered');
});

test('OrderStatus::labels retorna labels en español', function () {
    expect(OrderStatus::READY_FOR_PICKUP->label())->toBe('Listo para retirar')
        ->and(OrderStatus::PICKED_UP->label())->toBe('Retirado')
        ->and(OrderStatus::DISPATCHED->label())->toBe('En camino')
        ->and(OrderStatus::DELIVERED->label())->toBe('Entregado');
});

test('isAwaitingPayment es true para SERVED, PICKED_UP y DELIVERED', function () {
    expect(OrderStatus::SERVED->isAwaitingPayment())->toBeTrue()
        ->and(OrderStatus::PICKED_UP->isAwaitingPayment())->toBeTrue()
        ->and(OrderStatus::DELIVERED->isAwaitingPayment())->toBeTrue()
        ->and(OrderStatus::READY->isAwaitingPayment())->toBeFalse()
        ->and(OrderStatus::CONFIRMED->isAwaitingPayment())->toBeFalse();
});

test('chargeableStatuses incluye nuevos estados de fulfillment', function () {
    $chargeable = OrderStatus::chargeableStatuses();

    expect($chargeable)->toContain(OrderStatus::READY_FOR_PICKUP)
        ->and($chargeable)->toContain(OrderStatus::PICKED_UP)
        ->and($chargeable)->toContain(OrderStatus::DISPATCHED)
        ->and($chargeable)->toContain(OrderStatus::DELIVERED);
});
