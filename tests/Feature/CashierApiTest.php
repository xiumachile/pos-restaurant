<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Modules\Cashier\Domain\Entities\CashRegister;
use Modules\Cashier\Domain\Entities\CashMovement;
use Modules\Cashier\Domain\Entities\CashCount;
use Modules\Cashier\Domain\ValueObjects\MovementType;
use Modules\Cashier\Domain\ValueObjects\CashCountType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => '76.123.456-7',
        'legal_name' => 'Cashier API Test SpA',
        'trade_name' => 'Cashier API Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'CAPI',
        'name' => 'Cashier API Branch',
    ]);

    $this->cashier = User::create([
        'name' => 'Test Cashier',
        'email' => 'cashier-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    $this->manager = User::create([
        'name' => 'Test Manager',
        'email' => 'manager-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);

    $this->cashierToken = JWTAuth::fromUser($this->cashier);
    $this->managerToken = JWTAuth::fromUser($this->manager);
});

function cashierHeaders($useManager = false): array
{
    $token = $useManager ? test()->managerToken : test()->cashierToken;
    return [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

// ============================================
// GET /api/v1/cashier/registers
// ============================================

test('GET /api/v1/cashier/registers lista cajas de la sucursal', function () {
    CashRegister::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Caja 1',
        'code' => 'CAJA-01',
    ]);

    CashRegister::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Caja 2',
        'code' => 'CAJA-02',
    ]);

    $response = $this->withHeaders(cashierHeaders())
        ->getJson('/api/v1/cashier/registers');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

// ============================================
// POST /api/v1/cashier/registers
// ============================================

test('POST /api/v1/cashier/registers crea caja (solo manager)', function () {
    // Cashier no puede
    $response = $this->withHeaders(cashierHeaders(false))
        ->postJson('/api/v1/cashier/registers', [
            'name' => 'Caja Nueva',
            'code' => 'CAJA-NEW',
        ]);
    expect(in_array($response->status(), [401, 403]))->toBeTrue();

    // Manager sí puede - usar actingAs directamente para evitar problemas con tokens
    $response = $this->actingAs($this->manager, 'api')
        ->postJson('/api/v1/cashier/registers', [
            'name' => 'Caja Nueva',
            'code' => 'CAJA-NEW',
            'opening_amount_default' => 50000,
        ]);
    
    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Caja Nueva')
        ->assertJsonPath('data.code', 'CAJA-NEW');
});

test('POST /api/v1/cashier/registers valida campos requeridos', function () {
    $response = $this->withHeaders(cashierHeaders(true))
        ->postJson('/api/v1/cashier/registers', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'code']);
});

// ============================================
// POST /api/v1/cashier/movements
// ============================================

test('POST /api/v1/cashier/movements crea retiro válido', function () {
    $register = CashRegister::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Caja 1',
        'code' => 'CAJA-01',
    ]);

    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'register_id' => $register->id,
        'session_number' => 'CS-API-001',
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $response = $this->withHeaders(cashierHeaders())
        ->postJson('/api/v1/cashier/movements', [
            'session_uuid' => $session->uuid,
            'type' => 'withdrawal',
            'amount' => 10000,
            'reason' => 'Exceso en caja',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'withdrawal')
        ->assertJsonPath('data.amount', 10000)
        ->assertJsonPath('data.balance_after', 40000);
});

test('POST /api/v1/cashier/movements crea depósito válido', function () {
    $register = CashRegister::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Caja 1',
        'code' => 'CAJA-01',
    ]);

    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'register_id' => $register->id,
        'session_number' => 'CS-API-002',
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $response = $this->withHeaders(cashierHeaders())
        ->postJson('/api/v1/cashier/movements', [
            'session_uuid' => $session->uuid,
            'type' => 'deposit',
            'amount' => 20000,
            'reason' => 'Falta cambio',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'deposit')
        ->assertJsonPath('data.balance_after', 70000);
});

test('POST /api/v1/cashier/movements valida tipo inválido', function () {
    $register = CashRegister::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Caja 1',
        'code' => 'CAJA-01',
    ]);

    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'register_id' => $register->id,
        'session_number' => 'CS-API-003',
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $response = $this->withHeaders(cashierHeaders())
        ->postJson('/api/v1/cashier/movements', [
            'session_uuid' => $session->uuid,
            'type' => 'invalid',
            'amount' => 1000,
            'reason' => 'Test',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('type');
});

// ============================================
// GET /api/v1/cashier/movements/summary
// ============================================

test('GET /api/v1/cashier/movements/summary retorna resumen', function () {
    $register = CashRegister::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Caja 1',
        'code' => 'CAJA-01',
    ]);

    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'register_id' => $register->id,
        'session_number' => 'CS-API-004',
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    CashMovement::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'cash_session_id' => $session->id,
        'user_id' => $this->cashier->id,
        'type' => MovementType::WITHDRAWAL,
        'amount' => 10000,
        'reason' => 'Retiro',
        'balance_after' => 40000,
    ]);

    $response = $this->withHeaders(cashierHeaders())
        ->getJson("/api/v1/cashier/movements/summary?session_uuid={$session->uuid}");

    $response->assertOk()
        ->assertJsonPath('data.withdrawals_count', 1)
        ->assertJsonPath('data.withdrawals_total', 10000);
});

// ============================================
// POST /api/v1/cashier/counts
// ============================================

test('POST /api/v1/cashier/counts crea arqueo de apertura', function () {
    $register = CashRegister::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Caja 1',
        'code' => 'CAJA-01',
    ]);

    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'register_id' => $register->id,
        'session_number' => 'CS-API-005',
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $response = $this->withHeaders(cashierHeaders())
        ->postJson('/api/v1/cashier/counts', [
            'session_uuid' => $session->uuid,
            'type' => 'opening',
            'denominations' => [
                'bills' => [
                    '20000' => 1,
                    '10000' => 2,
                    '5000' => 2,
                    '2000' => 0,
                    '1000' => 0,
                ],
                'coins' => [
                    '500' => 0,
                    '100' => 0,
                    '50' => 0,
                    '10' => 0,
                    '5' => 0,
                    '1' => 0,
                ],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'opening')
        ->assertJsonPath('data.counted_amount', 50000)
        ->assertJsonPath('data.is_balanced', true);
});

test('POST /api/v1/cashier/counts detecta discrepancia', function () {
    $register = CashRegister::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Caja 1',
        'code' => 'CAJA-01',
    ]);

    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'register_id' => $register->id,
        'session_number' => 'CS-API-006',
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    // Contar solo $48.000 (faltante de $2.000)
    $response = $this->withHeaders(cashierHeaders())
        ->postJson('/api/v1/cashier/counts', [
            'session_uuid' => $session->uuid,
            'type' => 'closing',
            'denominations' => [
                'bills' => [
                    '20000' => 1,  // 20k
                    '10000' => 2,  // 20k
                    '5000' => 1,   // 5k
                    '2000' => 1,   // 2k
                    '1000' => 1,   // 1k
                ],
                'coins' => [
                    '500' => 0,
                    '100' => 0,
                    '50' => 0,
                    '10' => 0,
                    '5' => 0,
                    '1' => 0,
                ],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.has_discrepancy', true)
        ->assertJsonPath('data.has_shortage', true);
});

// ============================================
// POST /api/v1/cashier/counts/{uuid}/supervise
// ============================================

test('POST /api/v1/cashier/counts/{uuid}/supervise requiere explicación', function () {
    $register = CashRegister::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Caja 1',
        'code' => 'CAJA-01',
    ]);

    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'register_id' => $register->id,
        'session_number' => 'CS-API-007',
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $count = CashCount::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'cash_session_id' => $session->id,
        'user_id' => $this->cashier->id,
        'type' => CashCountType::CLOSING,
        'expected_amount' => 50000,
        'counted_amount' => 45000,
        'difference' => -5000,
        'has_discrepancy' => true,
    ]);

    // Sin explicación
    $response = $this->withHeaders(cashierHeaders(true))
        ->postJson("/api/v1/cashier/counts/{$count->uuid}/supervise", []);
    $response->assertStatus(422)
        ->assertJsonValidationErrors('explanation');

    // Con explicación válida
    $response = $this->withHeaders(cashierHeaders(true))
        ->postJson("/api/v1/cashier/counts/{$count->uuid}/supervise", [
            'explanation' => 'Faltante justificado por error de cambio en hora pico',
        ]);
    $response->assertOk()
        ->assertJsonPath('data.supervisor_name', $this->manager->name);
});

// ============================================
// GET /api/v1/cashier/dashboard
// ============================================

test('GET /api/v1/cashier/dashboard retorna dashboard completo', function () {
    $register = CashRegister::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Caja 1',
        'code' => 'CAJA-01',
    ]);

    CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'register_id' => $register->id,
        'session_number' => 'CS-API-008',
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $response = $this->withHeaders(cashierHeaders())
        ->getJson('/api/v1/cashier/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'current_session' => ['uuid', 'session_number', 'opening_amount', 'current_balance'],
                'registers',
                'statistics_today' => ['sessions_today', 'sessions_open', 'counts_today'],
            ],
        ]);
});

// ============================================
// Autorización
// ============================================

test('sin autenticacion retorna 401', function () {
    $response = $this->getJson('/api/v1/cashier/dashboard');
    $response->assertStatus(401);
});
