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
 * Tests de Fase 3 — fulfillment_channel en pedidos.
 *
 * Valida:
 * 1. Canal por defecto según tipo de pedido
 * 2. Override explícito de canal
 * 3. Validación de compatibilidad type ↔ channel
 * 4. Backfill de pedidos existentes
 */
beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'OFC-' . uniqid(),
        'legal_name' => 'Order Fulfillment Channel Test',
        'trade_name' => 'OFC Restaurant',
    ]);

    enableAllCapabilities($this->company);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'OFC',
        'name' => 'OFC Branch',
    ]);

    $this->table = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'table_number' => '1',
        'capacity' => 4,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón'],
    ]);

    $this->waiter = User::create([
        'name' => 'Waiter',
        'email' => 'waiter-ofc-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->token = JWTAuth::fromUser($this->waiter);
});

function ofcHeaders(string $token): array
{
    return [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

// ============================================
// CANAL POR DEFECTO SEGÚN TIPO
// ============================================

test('dine_in sin fulfillment_channel usa onsite por defecto', function () {
    $response = $this->withHeaders(ofcHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'dine_in',
            'table_uuid' => $this->table->uuid,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'dine_in')
        ->assertJsonPath('data.fulfillment_channel', 'onsite');

    $order = Order::where('uuid', $response->json('data.uuid'))->first();
    expect($order->fulfillment_channel)->toBe(FulfillmentChannel::ONSITE);
});

test('takeout sin fulfillment_channel usa pickup por defecto', function () {
    $response = $this->withHeaders(ofcHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'takeout',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'takeout')
        ->assertJsonPath('data.fulfillment_channel', 'pickup');
});

test('delivery sin fulfillment_channel usa delivery por defecto', function () {
    $response = $this->withHeaders(ofcHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'delivery',
            'customer_name' => 'Juan Pérez',
            'customer_phone' => '+56912345678',
            'delivery_address' => 'Av. Providencia 1234',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'delivery')
        ->assertJsonPath('data.fulfillment_channel', 'delivery');
});

// ============================================
// OVERRIDE EXPLÍCITO DE CANAL
// ============================================

test('dine_in puede sobreescribir canal a pickup (edge case)', function () {
    // Caso: cliente pide en mesa pero decide llevar
    $response = $this->withHeaders(ofcHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'dine_in',
            'table_uuid' => $this->table->uuid,
            'fulfillment_channel' => 'pickup',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'dine_in')
        ->assertJsonPath('data.fulfillment_channel', 'pickup');
});

test('takeout puede sobreescribir canal a onsite (edge case)', function () {
    // Caso: cliente pide para llevar pero decide quedarse
    $response = $this->withHeaders(ofcHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'takeout',
            'fulfillment_channel' => 'onsite',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'takeout')
        ->assertJsonPath('data.fulfillment_channel', 'onsite');
});

// ============================================
// VALIDACIÓN DE COMPATIBILIDAD TYPE ↔ CHANNEL
// ============================================

test('delivery NO puede tener canal onsite (422)', function () {
    $response = $this->withHeaders(ofcHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'delivery',
            'customer_name' => 'Juan Pérez',
            'customer_phone' => '+56912345678',
            'delivery_address' => 'Av. Providencia 1234',
            'fulfillment_channel' => 'onsite', // Inconsistente
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('fulfillment_channel');
});

test('delivery NO puede tener canal pickup (422)', function () {
    $response = $this->withHeaders(ofcHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'delivery',
            'customer_name' => 'Juan Pérez',
            'customer_phone' => '+56912345678',
            'delivery_address' => 'Av. Providencia 1234',
            'fulfillment_channel' => 'pickup', // Inconsistente
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('fulfillment_channel');
});

test('fulfillment_channel inválido falla con 422', function () {
    $response = $this->withHeaders(ofcHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'takeout',
            'fulfillment_channel' => 'invalid_channel',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('fulfillment_channel');
});

// ============================================
// BACKFILL DE PEDIDOS EXISTENTES
// ============================================

test('backfill asigna onsite a pedidos dine_in existentes', function () {
    // Crear pedido directamente sin fulfillment_channel (simula pedido antiguo)
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-BACKFILL-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'table_id' => $this->table->id,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 0,
    ]);

    // Verificar que el canal se asigna por defecto (default 'onsite' de la migración)
    $order->refresh();
    expect($order->fulfillment_channel)->toBe(FulfillmentChannel::ONSITE);
});

test('OrderType::defaultFulfillmentChannel retorna canal correcto', function () {
    expect(OrderType::DINE_IN->defaultFulfillmentChannel())->toBe(FulfillmentChannel::ONSITE)
        ->and(OrderType::TAKEOUT->defaultFulfillmentChannel())->toBe(FulfillmentChannel::PICKUP)
        ->and(OrderType::DELIVERY->defaultFulfillmentChannel())->toBe(FulfillmentChannel::DELIVERY);
});

test('FulfillmentChannel tiene labels correctos', function () {
    expect(FulfillmentChannel::ONSITE->label())->toBe('En el local')
        ->and(FulfillmentChannel::PICKUP->label())->toBe('Retiro en tienda')
        ->and(FulfillmentChannel::DELIVERY->label())->toBe('Entrega a domicilio');
});
