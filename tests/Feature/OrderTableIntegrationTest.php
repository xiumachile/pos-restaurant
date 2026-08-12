<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\ValueObjects\TableStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'ORD-INT-' . uniqid(),
        'legal_name' => 'Order Integration Company',
        'trade_name' => 'Order Integration Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'ORD-INT',
        'name' => 'Order Integration Branch',
    ]);

    $this->user = User::create([
        'name' => 'Order Integration User',
        'email' => 'orderint@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'admin',
    ]);

    $this->table = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón', 'zh' => '厅'],
        'table_number' => 'INT-01',
        'capacity' => 4,
    ]);

    $this->token = JWTAuth::fromUser($this->user);
});

test('al confirmar pedido la mesa pasa a occupied', function () {
    // Crear pedido dine_in
    $createResponse = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
        'Accept' => 'application/json',
    ])->postJson('/api/v1/orders', [
        'type' => 'dine_in',
        'table_uuid' => $this->table->uuid,
    ]);

    $orderUuid = $createResponse->json('data.uuid');

    // Confirmar pedido
    $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
        'Accept' => 'application/json',
    ])->postJson("/api/v1/orders/{$orderUuid}/confirm");

    // Verificar que la mesa está ocupada
    $this->table->refresh();
    expect($this->table->status)->toBe(TableStatus::Occupied);
    expect($this->table->current_order_id)->not->toBeNull();
});

test('al cerrar pedido la mesa vuelve a available', function () {
    // Crear pedido dine_in
    $createResponse = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
        'Accept' => 'application/json',
    ])->postJson('/api/v1/orders', [
        'type' => 'dine_in',
        'table_uuid' => $this->table->uuid,
    ]);

    $orderUuid = $createResponse->json('data.uuid');
    $headers = [
        'Authorization' => "Bearer {$this->token}",
        'Accept' => 'application/json',
    ];

    // Flujo completo
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$orderUuid}/confirm");
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$orderUuid}/prepare");
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$orderUuid}/ready");
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$orderUuid}/serve");
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$orderUuid}/pay");
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$orderUuid}/close");

    // Verificar que la mesa está disponible
    $this->table->refresh();
    expect($this->table->status)->toBe(TableStatus::Available);
    expect($this->table->current_order_id)->toBeNull();
});

test('al cancelar pedido confirmado la mesa se libera', function () {
    // Crear pedido dine_in
    $createResponse = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
        'Accept' => 'application/json',
    ])->postJson('/api/v1/orders', [
        'type' => 'dine_in',
        'table_uuid' => $this->table->uuid,
    ]);

    $orderUuid = $createResponse->json('data.uuid');
    $headers = [
        'Authorization' => "Bearer {$this->token}",
        'Accept' => 'application/json',
    ];

    // Confirmar (mesa se ocupa)
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$orderUuid}/confirm");
    $this->table->refresh();
    expect($this->table->status)->toBe(TableStatus::Occupied);

    // Cancelar
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$orderUuid}/cancel", [
        'reason' => 'Cliente se fue',
    ]);

    // Verificar que la mesa se liberó
    $this->table->refresh();
    expect($this->table->status)->toBe(TableStatus::Available);
    expect($this->table->current_order_id)->toBeNull();
});
