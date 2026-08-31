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

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'ORD-API-' . uniqid(),
        'legal_name' => 'Order API Company',
        'trade_name' => 'Order API Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'ORD-API',
        'name' => 'Order API Branch',
    ]);

    $this->user = User::create([
        'name' => 'Order API User',
        'email' => 'orderapi@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'admin',
    ]);

    $this->token = JWTAuth::fromUser($this->user);
});

function apiHeaders(string $token): array
{
    return [
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

// ============================================
// POST /api/v1/orders - Crear pedido
// ============================================

test('POST /api/v1/orders crea un pedido draft', function () {
    $response = $this->withHeaders(apiHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'takeout',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'takeout')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.is_editable', true);
});

test('POST /api/v1/orders requiere mesa para dine_in', function () {
    $response = $this->withHeaders(apiHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'dine_in',
        ]);

    $response->assertStatus(422);
});

test('POST /api/v1/orders crea pedido dine_in con mesa', function () {
    $table = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón', 'zh' => '厅'],
        'table_number' => 'T-01',
        'capacity' => 4,
    ]);

    $response = $this->withHeaders(apiHeaders($this->token))
        ->postJson('/api/v1/orders', [
            'type' => 'dine_in',
            'table_uuid' => $table->uuid,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'dine_in')
        ->assertJsonPath('data.table.uuid', $table->uuid);
});

// ============================================
// GET /api/v1/orders - Listar pedidos
// ============================================

test('GET /api/v1/orders lista pedidos del tenant', function () {
    // Crear 2 pedidos
    $this->withHeaders(apiHeaders($this->token))
        ->postJson('/api/v1/orders', ['type' => 'takeout']);
    
    $this->withHeaders(apiHeaders($this->token))
        ->postJson('/api/v1/orders', ['type' => 'takeout']);

    $response = $this->withHeaders(apiHeaders($this->token))
        ->getJson('/api/v1/orders');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

// ============================================
// GET /api/v1/orders/{uuid} - Ver detalle
// ============================================

test('GET /api/v1/orders/{uuid} retorna detalle del pedido', function () {
    $createResponse = $this->withHeaders(apiHeaders($this->token))
        ->postJson('/api/v1/orders', ['type' => 'takeout']);

    $uuid = $createResponse->json('data.uuid');

    $response = $this->withHeaders(apiHeaders($this->token))
        ->getJson("/api/v1/orders/{$uuid}");

    $response->assertOk()
        ->assertJsonPath('data.uuid', $uuid)
        ->assertJsonPath('data.status', 'draft');
});

// ============================================
// DELETE /api/v1/orders/{uuid} - Eliminar draft
// ============================================

test('DELETE /api/v1/orders/{uuid} elimina pedido draft', function () {
    $createResponse = $this->withHeaders(apiHeaders($this->token))
        ->postJson('/api/v1/orders', ['type' => 'takeout']);

    $uuid = $createResponse->json('data.uuid');

    $response = $this->withHeaders(apiHeaders($this->token))
        ->deleteJson("/api/v1/orders/{$uuid}");

    $response->assertOk();
    
    expect(Order::where('uuid', $uuid)->exists())->toBeFalse();
});

test('DELETE /api/v1/orders/{uuid} deniega eliminar pedido confirmado', function () {
    $createResponse = $this->withHeaders(apiHeaders($this->token))
        ->postJson('/api/v1/orders', ['type' => 'takeout']);

    $uuid = $createResponse->json('data.uuid');

    // Confirmar el pedido
    $this->withHeaders(apiHeaders($this->token))
        ->postJson("/api/v1/orders/{$uuid}/confirm");

    // Intentar eliminar
    $response = $this->withHeaders(apiHeaders($this->token))
        ->deleteJson("/api/v1/orders/{$uuid}");

    $response->assertStatus(422);
});

// ============================================
// Sin token retorna 401
// ============================================

test('GET /api/v1/orders sin token retorna 401', function () {
    $response = $this->getJson('/api/v1/orders');
    $response->assertStatus(401);
});

// ============================================
// TESTS: has_kitchen_display capability
// ============================================

test('pedido salta directo a READY si has_kitchen_display OFF', function () {
    // Crear empresa SIN has_kitchen_display
    $companyNoKDS = Company::create([
        'tax_id' => 'NO-KDS-' . uniqid(),
        'legal_name' => 'No KDS Company',
        'trade_name' => 'No KDS',
    ]);

    enableCapabilities($companyNoKDS, ['can_split_bills', 'requires_cashier_session', 'can_accept_tips']);

    $branchNoKDS = Branch::create([
        'company_id' => $companyNoKDS->id,
        'code' => 'NO-KDS',
        'name' => 'No KDS Branch',
    ]);

    $cashier = User::create([
        'name' => 'Cashier No KDS',
        'email' => 'cashier-no-kds-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $companyNoKDS->id,
        'branch_id' => $branchNoKDS->id,
        'role' => 'cashier',
    ]);

    $token = JWTAuth::fromUser($cashier);

    // Crear pedido
    $order = Order::create([
        'company_id' => $companyNoKDS->id,
        'branch_id' => $branchNoKDS->id,
        'order_number' => 'ORD-NO-KDS-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    // Confirmar pedido (debería saltar directo a READY)
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ])->putJson("/api/v1/orders/{$order->uuid}", [
        'status' => 'confirmed',
    ]);

    $response->assertOk();

    // Verificar que saltó directo a READY (no se quedó en CONFIRMED)
    $order->refresh();
    expect($order->status)->toBe(OrderStatus::READY);
});

test('pedido pasa por CONFIRMED si has_kitchen_display ON', function () {
    // Crear empresa CON has_kitchen_display
    $companyWithKDS = Company::create([
        'tax_id' => 'WITH-KDS-' . uniqid(),
        'legal_name' => 'With KDS Company',
        'trade_name' => 'With KDS',
    ]);

    enableCapabilities($companyWithKDS, ['can_split_bills', 'requires_cashier_session', 'can_accept_tips', 'has_kitchen_display']);

    $branchWithKDS = Branch::create([
        'company_id' => $companyWithKDS->id,
        'code' => 'WITH-KDS',
        'name' => 'With KDS Branch',
    ]);

    $cashier = User::create([
        'name' => 'Cashier With KDS',
        'email' => 'cashier-with-kds-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $companyWithKDS->id,
        'branch_id' => $branchWithKDS->id,
        'role' => 'cashier',
    ]);

    $token = JWTAuth::fromUser($cashier);

    // Crear pedido
    $order = Order::create([
        'company_id' => $companyWithKDS->id,
        'branch_id' => $branchWithKDS->id,
        'order_number' => 'ORD-WITH-KDS-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    // Confirmar pedido (debería quedarse en CONFIRMED para KDS)
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ])->putJson("/api/v1/orders/{$order->uuid}", [
        'status' => 'confirmed',
    ]);

    $response->assertOk();

    // Verificar que se quedó en CONFIRMED (esperando que cocina lo tome)
    $order->refresh();
    expect($order->status)->toBe(OrderStatus::CONFIRMED);
});
