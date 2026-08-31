<?php

use Modules\Branches\Domain\Entities\Branch;
use Modules\Cashier\Domain\Entities\CashMovement;
use Modules\Cashier\Domain\ValueObjects\MovementType;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Empresa con 2 sucursales (para probar cross-branch isolation)
    $this->company = Company::create([
        'tax_id' => 'BUGFIX-' . uniqid(),
        'legal_name' => 'Bugfix Test Company',
        'trade_name' => 'Bugfix Test',
    ]);

    enableAllCapabilities($this->company);

    $this->branchA = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'BF-A',
        'name' => 'Branch A',
    ]);

    $this->branchB = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'BF-B',
        'name' => 'Branch B',
    ]);

    $this->cashierA = User::create([
        'name' => 'Cashier A',
        'email' => 'bugfix-cashier-a-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'role' => 'cashier',
    ]);

    $this->cashierB = User::create([
        'name' => 'Cashier B',
        'email' => 'bugfix-cashier-b-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branchB->id,
        'role' => 'cashier',
    ]);

    $this->tokenA = JWTAuth::fromUser($this->cashierA);
    $this->tokenB = JWTAuth::fromUser($this->cashierB);
});

function bugfixHeaders(string $token): array
{
    return [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ];
}

// ============================================
// TEST 1: calculateExpectedAmountForClose incluye movimientos
// ============================================

test('calculateExpectedAmountForClose incluye movimientos de caja', function () {
    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->cashierA->id,
        'session_number' => 'CS-BF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 100000,
        'opened_at' => now(),
    ]);

    // Retiro de 20000
    CashMovement::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'cash_session_id' => $session->id,
        'user_id' => $this->cashierA->id,
        'type' => MovementType::WITHDRAWAL,
        'amount' => 20000,
        'reason' => 'Pago a proveedor',
        'balance_after' => 80000,
    ]);

    // Depósito de 5000
    CashMovement::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'cash_session_id' => $session->id,
        'user_id' => $this->cashierA->id,
        'type' => MovementType::DEPOSIT,
        'amount' => 5000,
        'reason' => 'Ajuste',
        'balance_after' => 85000,
    ]);

    // Esperado: 100000 - 20000 + 5000 = 85000
    $expected = $session->calculateExpectedAmountForClose();

    expect($expected)->toBe(85000.0);
});

// ============================================
// TEST 2: Consistencia entre métodos con movimientos
// ============================================

test('calculateExpectedAmountForClose y calculateExpectedCashBalance coinciden sin propinas', function () {
    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->cashierA->id,
        'session_number' => 'CS-BF-2-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    // Sin propinas (sin TipPayouts), ambos métodos deben coincidir
    // (la diferencia entre ellos es solo cómo tratan las propinas payroll)
    CashMovement::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'cash_session_id' => $session->id,
        'user_id' => $this->cashierA->id,
        'type' => MovementType::WITHDRAWAL,
        'amount' => 10000,
        'reason' => 'Retiro',
        'balance_after' => 40000,
    ]);

    CashMovement::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'cash_session_id' => $session->id,
        'user_id' => $this->cashierA->id,
        'type' => MovementType::DEPOSIT,
        'amount' => 3000,
        'reason' => 'Depósito',
        'balance_after' => 43000,
    ]);

    $forClose = $session->calculateExpectedAmountForClose();
    $cashBalance = $session->calculateExpectedCashBalance();

    // Sin TipPayouts, ambos deben ser iguales
    expect($forClose)->toBe($cashBalance)
        ->and($forClose)->toBe(43000.0); // 50000 - 10000 + 3000
});

// ============================================
// TEST 3: Cross-branch isolation (SEGURIDAD)
// ============================================

test('close endpoint valida branch_id - cajero B no puede cerrar sesión de sucursal A', function () {
    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->cashierA->id,
        'session_number' => 'CS-BF-3-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 100000,
        'opened_at' => now(),
    ]);

    // Cajero B intenta cerrar sesión de sucursal A (mismo company_id, diferente branch_id)
    $response = $this->withHeaders(bugfixHeaders($this->tokenB))
        ->postJson("/api/v1/cash-sessions/{$session->uuid}/close", [
            'closing_amount' => 100000,
        ]);

    // Debe fallar con 404 (filtro de branch_id no la encuentra)
    $response->assertStatus(404);

    // La sesión debe seguir abierta
    $session->refresh();
    expect($session->status)->toBe(CashSessionStatus::OPEN);
});

// ============================================
// TEST 4: Cierre exitoso dentro de la misma sucursal
// ============================================

test('close endpoint permite cerrar sesión de la misma sucursal', function () {
    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->cashierA->id,
        'session_number' => 'CS-BF-4-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 100000,
        'opened_at' => now(),
    ]);

    // Cajero A cierra su propia sesión
    $response = $this->withHeaders(bugfixHeaders($this->tokenA))
        ->postJson("/api/v1/cash-sessions/{$session->uuid}/close", [
            'closing_amount' => 95000,
            'notes' => 'Cierre de turno',
        ]);

    $response->assertOk();

    // Verificar que se cerró con los datos correctos
    $session->refresh();
    expect($session->status)->toBe(CashSessionStatus::CLOSED)
        ->and((float) $session->closing_amount)->toBe(95000.0)
        ->and((float) $session->expected_amount)->toBe(100000.0)
        ->and((float) $session->difference)->toBe(-5000.0); // 95000 - 100000
});
