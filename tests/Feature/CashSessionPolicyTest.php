<?php

use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'POL-' . uniqid(),
        'legal_name' => 'Policy Test Company',
        'trade_name' => 'Policy Test',
    ]);

    enableAllCapabilities($this->company);

    // Crear 2 branches para probar cross-branch isolation
    $this->branchA = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'POL-A',
        'name' => 'Branch A',
    ]);

    $this->branchB = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'POL-B',
        'name' => 'Branch B',
    ]);

    // Usuarios de branch A con diferentes roles
    $this->cashierA = User::create([
        'name' => 'Cashier A',
        'email' => 'cashier-a-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'role' => 'cashier',
    ]);

    $this->waiterA = User::create([
        'name' => 'Waiter A',
        'email' => 'waiter-a-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'role' => 'waiter',
    ]);

    $this->managerA = User::create([
        'name' => 'Manager A',
        'email' => 'manager-a-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'role' => 'manager',
    ]);

    // Cajero de branch B (para cross-branch isolation)
    $this->cashierB = User::create([
        'name' => 'Cashier B',
        'email' => 'cashier-b-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branchB->id,
        'role' => 'cashier',
    ]);
});

function cashSessionPolicyHeaders(string $token): array
{
    return [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

// ============================================
// ROLES AUTORIZADOS (cashier, admin, manager)
// ============================================

test('cashier puede abrir sesión de caja', function () {
    $token = JWTAuth::fromUser($this->cashierA);

    $response = $this->withHeaders(cashSessionPolicyHeaders($token))
        ->postJson('/api/v1/cash-sessions/open', [
            'opening_amount' => 50000,
        ]);

    $response->assertStatus(201);
});

test('cashier puede cerrar sesión de caja', function () {
    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->cashierA->id,
        'session_number' => 'CS-POL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $token = JWTAuth::fromUser($this->cashierA);

    $response = $this->withHeaders(cashSessionPolicyHeaders($token))
        ->postJson("/api/v1/cash-sessions/{$session->uuid}/close", [
            'closing_amount' => 50000,
        ]);

    $response->assertOk();
});

test('manager puede abrir y cerrar sesión de caja', function () {
    $token = JWTAuth::fromUser($this->managerA);

    // Abrir
    $openResponse = $this->withHeaders(cashSessionPolicyHeaders($token))
        ->postJson('/api/v1/cash-sessions/open', [
            'opening_amount' => 50000,
        ]);

    $openResponse->assertStatus(201);
    $sessionUuid = $openResponse->json('data.uuid');

    // Cerrar
    $closeResponse = $this->withHeaders(cashSessionPolicyHeaders($token))
        ->postJson("/api/v1/cash-sessions/{$sessionUuid}/close", [
            'closing_amount' => 50000,
        ]);

    $closeResponse->assertOk();
});

// ============================================
// ROLES NO AUTORIZADOS (waiter)
// ============================================

test('waiter NO puede abrir sesión de caja (403)', function () {
    $token = JWTAuth::fromUser($this->waiterA);

    $response = $this->withHeaders(cashSessionPolicyHeaders($token))
        ->postJson('/api/v1/cash-sessions/open', [
            'opening_amount' => 50000,
        ]);

    $response->assertStatus(403);
});

test('waiter NO puede cerrar sesión de caja (403)', function () {
    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->cashierA->id,
        'session_number' => 'CS-POL-2-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $token = JWTAuth::fromUser($this->waiterA);

    $response = $this->withHeaders(cashSessionPolicyHeaders($token))
        ->postJson("/api/v1/cash-sessions/{$session->uuid}/close", [
            'closing_amount' => 50000,
        ]);

    $response->assertStatus(403);
});

test('waiter puede ver sesión actual (200)', function () {
    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->cashierA->id,
        'session_number' => 'CS-POL-3-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $token = JWTAuth::fromUser($this->waiterA);

    $response = $this->withHeaders(cashSessionPolicyHeaders($token))
        ->getJson('/api/v1/cash-sessions/current');

    $response->assertOk()
        ->assertJsonPath('data.uuid', $session->uuid);
});

// ============================================
// CROSS-BRANCH ISOLATION
// ============================================

test('cajero B NO puede cerrar sesión de branch A (cross-branch, 404)', function () {
    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id, // Sesión en branch A
        'user_id' => $this->cashierA->id,
        'session_number' => 'CS-POL-4-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $token = JWTAuth::fromUser($this->cashierB); // Cajero de branch B

    $response = $this->withHeaders(cashSessionPolicyHeaders($token))
        ->postJson("/api/v1/cash-sessions/{$session->uuid}/close", [
            'closing_amount' => 50000,
        ]);

    // Debe fallar con 404 (filtro de branch_id no encuentra la sesión)
    $response->assertStatus(404);

    // Verificar que la sesión sigue abierta
    $session->refresh();
    expect($session->status)->toBe(CashSessionStatus::OPEN);
});

test('cajero B NO puede ver sesión actual de branch A (aislamiento)', function () {
    $sessionA = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->cashierA->id,
        'session_number' => 'CS-POL-5-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $token = JWTAuth::fromUser($this->cashierB);

    $response = $this->withHeaders(cashSessionPolicyHeaders($token))
        ->getJson('/api/v1/cash-sessions/current');

    // Cajero B no debe ver la sesión de branch A (debe retornar null)
    $response->assertOk()
        ->assertJsonPath('data', null);
});
