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
    // Empresa CON has_kitchen_display
    $this->companyWithKDS = Company::create([
        'tax_id' => 'KDS-ON-' . uniqid(),
        'legal_name' => 'With KDS Company',
        'trade_name' => 'With KDS',
    ]);

    enableCapabilities($this->companyWithKDS, ['can_split_bills', 'requires_cashier_session', 'can_accept_tips', 'has_kitchen_display']);

    $this->branchWithKDS = Branch::create([
        'company_id' => $this->companyWithKDS->id,
        'code' => 'KDS-ON',
        'name' => 'With KDS Branch',
    ]);

    $this->kitchenUserWithKDS = User::create([
        'name' => 'Kitchen User With KDS',
        'email' => 'kitchen-with-kds-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyWithKDS->id,
        'branch_id' => $this->branchWithKDS->id,
        'role' => 'kitchen',
    ]);

    // Empresa SIN has_kitchen_display
    $this->companyNoKDS = Company::create([
        'tax_id' => 'KDS-OFF-' . uniqid(),
        'legal_name' => 'No KDS Company',
        'trade_name' => 'No KDS',
    ]);

    enableCapabilities($this->companyNoKDS, ['can_split_bills', 'requires_cashier_session', 'can_accept_tips']);

    $this->branchNoKDS = Branch::create([
        'company_id' => $this->companyNoKDS->id,
        'code' => 'KDS-OFF',
        'name' => 'No KDS Branch',
    ]);

    $this->kitchenUserNoKDS = User::create([
        'name' => 'Kitchen User No KDS',
        'email' => 'kitchen-no-kds-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyNoKDS->id,
        'branch_id' => $this->branchNoKDS->id,
        'role' => 'kitchen',
    ]);

    $this->tokenWithKDS = JWTAuth::fromUser($this->kitchenUserWithKDS);
    $this->tokenNoKDS = JWTAuth::fromUser($this->kitchenUserNoKDS);
});

function kitchenCapabilityHeaders(string $token): array
{
    return [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ];
}

// ============================================
// CAPABILITY ON: acceso permitido
// ============================================

test('kitchen/queue funciona si has_kitchen_display ON', function () {
    Order::create([
        'company_id' => $this->companyWithKDS->id,
        'branch_id' => $this->branchWithKDS->id,
        'order_number' => 'ORD-KDS-1-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
        'confirmed_at' => now(),
    ]);

    $response = $this->withHeaders(kitchenCapabilityHeaders($this->tokenWithKDS))
        ->getJson('/api/v1/kitchen/queue');

    $response->assertOk()
        ->assertJsonStructure(['data']);
});

test('kitchen/stats funciona si has_kitchen_display ON', function () {
    $response = $this->withHeaders(kitchenCapabilityHeaders($this->tokenWithKDS))
        ->getJson('/api/v1/kitchen/stats');

    $response->assertOk()
        ->assertJsonStructure(['data']);
});

// ============================================
// CAPABILITY OFF: acceso bloqueado (403)
// ============================================

test('kitchen/queue retorna 403 si has_kitchen_display OFF', function () {
    $response = $this->withHeaders(kitchenCapabilityHeaders($this->tokenNoKDS))
        ->getJson('/api/v1/kitchen/queue');

    $response->assertStatus(403);
});

test('kitchen/stats retorna 403 si has_kitchen_display OFF', function () {
    $response = $this->withHeaders(kitchenCapabilityHeaders($this->tokenNoKDS))
        ->getJson('/api/v1/kitchen/stats');

    $response->assertStatus(403);
});

test('kitchen/history retorna 403 si has_kitchen_display OFF', function () {
    $response = $this->withHeaders(kitchenCapabilityHeaders($this->tokenNoKDS))
        ->getJson('/api/v1/kitchen/history');

    $response->assertStatus(403);
});

test('kitchen/tables-today retorna 403 si has_kitchen_display OFF', function () {
    $response = $this->withHeaders(kitchenCapabilityHeaders($this->tokenNoKDS))
        ->getJson('/api/v1/kitchen/tables-today');

    $response->assertStatus(403);
});
