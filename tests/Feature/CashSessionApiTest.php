<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Tenant A
    $this->company = Company::create([
        'tax_id' => 'CASH-SESSION-' . uniqid(),
        'legal_name' => 'Cash Session Company',
        'trade_name' => 'Cash Session Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'CS-A',
        'name' => 'Cash Session Branch',
    ]);

    $this->cashier = User::create([
        'name' => 'Test Cashier',
        'email' => 'cash-session-cashier-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    // Tenant B
    $this->companyB = Company::create([
        'tax_id' => 'CASH-SESSION-B-' . uniqid(),
        'legal_name' => 'Cash Session Company B',
        'trade_name' => 'Cash Session Restaurant B',
    ]);

    $this->branchB = Branch::create([
        'company_id' => $this->companyB->id,
        'code' => 'CS-B',
        'name' => 'Cash Session Branch B',
    ]);

    $this->cashierB = User::create([
        'name' => 'Test Cashier B',
        'email' => 'cash-session-cashier-b-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'role' => 'cashier',
    ]);

    $this->token = JWTAuth::fromUser($this->cashier);
    $this->tokenB = JWTAuth::fromUser($this->cashierB);
});

function cashSessionApiHeaders(?string $token = null): array
{
    return [
        'Authorization' => 'Bearer ' . ($token ?? test()->token),
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

function cashSessionApiCreateOpenSession($test): CashSession
{
    return CashSession::create([
        'company_id' => $test->company->id,
        'branch_id' => $test->branch->id,
        'user_id' => $test->cashier->id,
        'session_number' => 'CS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'expected_amount' => 50000,
        'opened_at' => now(),
    ]);
}

// ============================================
// Cash Sessions
// ============================================

test('POST /api/v1/cash-sessions/open abre sesion de caja', function () {
    $response = $this->withHeaders(cashSessionApiHeaders())
        ->postJson('/api/v1/cash-sessions/open', [
            'opening_amount' => 50000,
            'notes' => 'Apertura turno mañana',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.opening_amount', 50000);
});

test('POST /api/v1/cash-sessions/open deniega si ya hay sesion abierta', function () {
    $this->withHeaders(cashSessionApiHeaders())
        ->postJson('/api/v1/cash-sessions/open', [
            'opening_amount' => 50000,
        ]);

    $response = $this->withHeaders(cashSessionApiHeaders())
        ->postJson('/api/v1/cash-sessions/open', [
            'opening_amount' => 30000,
        ]);

    $response->assertStatus(422);
});

test('GET /api/v1/cash-sessions/current retorna sesion abierta', function () {
    $this->withHeaders(cashSessionApiHeaders())
        ->postJson('/api/v1/cash-sessions/open', [
            'opening_amount' => 50000,
        ]);

    $response = $this->withHeaders(cashSessionApiHeaders())
        ->getJson('/api/v1/cash-sessions/current');

    $response->assertOk()
        ->assertJsonPath('data.status', 'open');
});

test('GET /api/v1/cash-sessions/current retorna null si no hay sesion abierta', function () {
    $response = $this->withHeaders(cashSessionApiHeaders())
        ->getJson('/api/v1/cash-sessions/current');

    $response->assertOk()
        ->assertJsonPath('data', null);
});

test('POST /api/v1/cash-sessions/{uuid}/close cierra sesion abierta', function () {
    $session = cashSessionApiCreateOpenSession($this);

    $response = $this->withHeaders(cashSessionApiHeaders())
        ->postJson("/api/v1/cash-sessions/{$session->uuid}/close", [
            'closing_amount' => 55000,
            'notes' => 'Cierre turno',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'closed');
});

// ============================================
// Cross-tenant isolation
// ============================================

test('POST /api/v1/cash-sessions/open usuario B crea sesion solo en su branch', function () {
    $response = $this->withHeaders(cashSessionApiHeaders($this->tokenB))
        ->postJson('/api/v1/cash-sessions/open', [
            'opening_amount' => 40000,
        ]);

    $response->assertStatus(201);

    expect(CashSession::where('branch_id', $this->branch->id)->count())->toBe(0);
    expect(CashSession::where('branch_id', $this->branchB->id)->count())->toBe(1);
});

test('POST /api/v1/cash-sessions/{uuid}/close usuario B no puede cerrar sesion de empresa A', function () {
    $sessionA = cashSessionApiCreateOpenSession($this);

    $response = $this->withHeaders(cashSessionApiHeaders($this->tokenB))
        ->postJson("/api/v1/cash-sessions/{$sessionA->uuid}/close", [
            'closing_amount' => 55000,
        ]);

    expect($response->status())->toBeIn([403, 404, 422]);
});

test('GET /api/v1/cash-sessions/current usuario B no ve sesion de empresa A', function () {
    cashSessionApiCreateOpenSession($this);

    $response = $this->withHeaders(cashSessionApiHeaders($this->tokenB))
        ->getJson('/api/v1/cash-sessions/current');

    $response->assertOk()
        ->assertJsonPath('data', null);
});

test('sin autenticacion retorna 401', function () {
    $response = $this->getJson('/api/v1/cash-sessions/current');
    $response->assertStatus(401);
});
