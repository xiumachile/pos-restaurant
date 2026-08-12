<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'ORD-TRANS-' . uniqid(),
        'legal_name' => 'Order Transitions Company',
        'trade_name' => 'Order Transitions Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'ORD-TR',
        'name' => 'Order Transitions Branch',
    ]);

    $this->user = User::create([
        'name' => 'Order Transitions User',
        'email' => 'ordertrans@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'admin',
    ]);

    $this->token = JWTAuth::fromUser($this->user);
});

function createDraftOrder(): string
{
    $response = test()->withHeaders([
        'Authorization' => "Bearer " . test()->token,
        'Accept' => 'application/json',
    ])->postJson('/api/v1/orders', ['type' => 'takeout']);

    return $response->json('data.uuid');
}

test('POST /api/v1/orders/{uuid}/confirm transiciona draft a confirmed', function () {
    $uuid = createDraftOrder();

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
        'Accept' => 'application/json',
    ])->postJson("/api/v1/orders/{$uuid}/confirm");

    $response->assertOk()
        ->assertJsonPath('data.status', 'confirmed');
});

test('ciclo completo de transiciones via API funciona', function () {
    $uuid = createDraftOrder();
    $headers = [
        'Authorization' => "Bearer {$this->token}",
        'Accept' => 'application/json',
    ];

    // draft → confirmed
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/confirm")
        ->assertJsonPath('data.status', 'confirmed');

    // confirmed → preparing
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/prepare")
        ->assertJsonPath('data.status', 'preparing');

    // preparing → ready
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/ready")
        ->assertJsonPath('data.status', 'ready');

    // ready → served
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/serve")
        ->assertJsonPath('data.status', 'served');

    // served → paid
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/pay")
        ->assertJsonPath('data.status', 'paid');

    // paid → closed
    $this->withHeaders($headers)->postJson("/api/v1/orders/{$uuid}/close")
        ->assertJsonPath('data.status', 'closed');
});

test('deniega transicion invalida via API', function () {
    $uuid = createDraftOrder();

    // Intentar pasar de draft a preparing (inválido)
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
        'Accept' => 'application/json',
    ])->postJson("/api/v1/orders/{$uuid}/prepare");

    $response->assertStatus(422);
});

test('POST /api/v1/orders/{uuid}/cancel requiere razon', function () {
    $uuid = createDraftOrder();

    // Sin razón
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
        'Accept' => 'application/json',
    ])->postJson("/api/v1/orders/{$uuid}/cancel", []);

    $response->assertStatus(422);
});

test('POST /api/v1/orders/{uuid}/cancel con razon funciona', function () {
    $uuid = createDraftOrder();

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
        'Accept' => 'application/json',
    ])->postJson("/api/v1/orders/{$uuid}/cancel", [
        'reason' => 'Cliente cambió de opinión',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancellation_reason', 'Cliente cambió de opinión');
});
