<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Tables\Domain\Entities\RestaurantTable;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

/**
 * Tests de Fase 2 — Order Core sin Mesa.
 *
 * Valida que el sistema soporte correctamente pedidos de los 3 tipos:
 * - dine_in: con mesa (flujo tradicional)
 * - takeout: sin mesa, con/sin datos de cliente
 * - delivery: sin mesa, con datos de cliente y dirección requeridos
 */
beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'OWT-' . uniqid(),
        'legal_name' => 'Order Without Table Test',
        'trade_name' => 'OWT Restaurant',
    ]);

    enableAllCapabilities($this->company);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'OWT',
        'name' => 'OWT Branch',
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
        'email' => 'waiter-owt-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'admin',  // Admin para tests de creación (no probamos autorización de rol aquí)
    ]);

    $this->token = JWTAuth::fromUser($this->waiter);
});

function owtHeaders(string $token): array
{
    return [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

// ============================================
// DINE_IN (con mesa, flujo tradicional)
// ============================================

test('dine_in con mesa se crea correctamente (201)', function () {
    $response = $this->withHeaders(owtHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'dine_in',
            'table_uuid' => $this->table->uuid,
            'notes' => 'Mesa cerca de la ventana',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'dine_in')
        ->assertJsonPath('data.notes', 'Mesa cerca de la ventana');

    // Verificar que se guardó la mesa
    $order = Order::where('uuid', $response->json('data.uuid'))->first();
    expect($order->table_id)->toBe($this->table->id)
        ->and($order->customer_name)->toBeNull()
        ->and($order->delivery_address)->toBeNull();
});

test('dine_in sin mesa falla con 422', function () {
    $response = $this->withHeaders(owtHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'dine_in',
            // Falta table_uuid
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('table_uuid');
});

test('dine_in con delivery_address falla con 422', function () {
    $response = $this->withHeaders(owtHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'dine_in',
            'table_uuid' => $this->table->uuid,
            'delivery_address' => 'No debería permitirse',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('delivery_address');
});

// ============================================
// TAKEOUT (sin mesa, campos opcionales)
// ============================================

test('takeout simple sin datos de cliente se crea (201)', function () {
    // Valida compatibilidad con tests existentes
    $response = $this->withHeaders(owtHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'takeout',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'takeout');

    $order = Order::where('uuid', $response->json('data.uuid'))->first();
    expect($order->table_id)->toBeNull()
        ->and($order->customer_name)->toBeNull()
        ->and($order->customer_phone)->toBeNull()
        ->and($order->pickup_at)->toBeNull();
});

test('takeout con datos de cliente y pickup_at se crea (201)', function () {
    $pickupAt = now()->addHour()->toIso8601String();

    $response = $this->withHeaders(owtHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'takeout',
            'customer_name' => 'Juan Pérez',
            'customer_phone' => '+56912345678',
            'pickup_at' => $pickupAt,
            'notes' => 'Sin cebolla',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'takeout')
        ->assertJsonPath('data.customer_name', 'Juan Pérez')
        ->assertJsonPath('data.customer_phone', '+56912345678');
});

test('takeout CON mesa falla con 422', function () {
    $response = $this->withHeaders(owtHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'takeout',
            'table_uuid' => $this->table->uuid, // No permitido
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('table_uuid');
});

test('takeout con pickup_at en el pasado falla con 422', function () {
    $response = $this->withHeaders(owtHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'takeout',
            'pickup_at' => now()->subHour()->toIso8601String(),
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('pickup_at');
});

// ============================================
// DELIVERY (sin mesa, con datos requeridos)
// ============================================

test('delivery con todos los datos requeridos se crea (201)', function () {
    $response = $this->withHeaders(owtHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'delivery',
            'customer_name' => 'María García',
            'customer_phone' => '+56987654321',
            'delivery_address' => 'Av. Providencia 1234, Depto 501',
            'delivery_notes' => 'Tocar timbre 501',
            'notes' => 'Sin cebolla',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'delivery')
        ->assertJsonPath('data.customer_name', 'María García')
        ->assertJsonPath('data.customer_phone', '+56987654321')
        ->assertJsonPath('data.delivery_address', 'Av. Providencia 1234, Depto 501')
        ->assertJsonPath('data.delivery_notes', 'Tocar timbre 501');

    $order = Order::where('uuid', $response->json('data.uuid'))->first();
    expect($order->table_id)->toBeNull()
        ->and($order->pickup_at)->toBeNull();
});

test('delivery sin customer_name falla con 422', function () {
    $response = $this->withHeaders(owtHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'delivery',
            // Falta customer_name
            'customer_phone' => '+56987654321',
            'delivery_address' => 'Av. Providencia 1234',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('customer_name');
});

test('delivery sin customer_phone falla con 422', function () {
    $response = $this->withHeaders(owtHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'delivery',
            'customer_name' => 'María García',
            // Falta customer_phone
            'delivery_address' => 'Av. Providencia 1234',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('customer_phone');
});

test('delivery sin delivery_address falla con 422', function () {
    $response = $this->withHeaders(owtHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'delivery',
            'customer_name' => 'María García',
            'customer_phone' => '+56987654321',
            // Falta delivery_address
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('delivery_address');
});

test('delivery con mesa falla con 422', function () {
    $response = $this->withHeaders(owtHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'delivery',
            'customer_name' => 'María García',
            'customer_phone' => '+56987654321',
            'delivery_address' => 'Av. Providencia 1234',
            'table_uuid' => $this->table->uuid, // No permitido
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('table_uuid');
});

test('delivery con pickup_at falla con 422', function () {
    $response = $this->withHeaders(owtHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'delivery',
            'customer_name' => 'María García',
            'customer_phone' => '+56987654321',
            'delivery_address' => 'Av. Providencia 1234',
            'pickup_at' => now()->addHour()->toIso8601String(), // No permitido en delivery
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('pickup_at');
});

// ============================================
// OrderType enum — validaciones de dominio
// ============================================

test('OrderType::DINE_IN requiere mesa', function () {
    expect(OrderType::DINE_IN->requiresTable())->toBeTrue()
        ->and(OrderType::DINE_IN->forbidsTable())->toBeFalse();
});

test('OrderType::TAKEOUT prohíbe mesa', function () {
    expect(OrderType::TAKEOUT->requiresTable())->toBeFalse()
        ->and(OrderType::TAKEOUT->forbidsTable())->toBeTrue();
});

test('OrderType::DELIVERY prohíbe mesa', function () {
    expect(OrderType::DELIVERY->requiresTable())->toBeFalse()
        ->and(OrderType::DELIVERY->forbidsTable())->toBeTrue();
});

test('defaultFulfillmentChannel mapea correctamente', function () {
    expect(OrderType::DINE_IN->defaultFulfillmentChannel()->value)->toBe('onsite')
        ->and(OrderType::TAKEOUT->defaultFulfillmentChannel()->value)->toBe('pickup')
        ->and(OrderType::DELIVERY->defaultFulfillmentChannel()->value)->toBe('delivery');
});
