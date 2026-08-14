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
use Modules\Cashier\Domain\ValueObjects\Denomination;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => '76.123.456-7',
        'legal_name' => 'Cashier Test SpA',
        'trade_name' => 'Cashier Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'CASH',
        'name' => 'Cashier Branch',
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
});

// ============================================
// Denomination Value Object
// ============================================

test('Denomination tiene billetes y monedas CLP', function () {
    expect(Denomination::bills())->toContain(20000, 10000, 5000, 2000, 1000);
    expect(Denomination::coins())->toContain(500, 100, 50, 10, 5, 1);
    expect(count(Denomination::all()))->toBe(11);
});

test('Denomination identifica correctamente billetes y monedas', function () {
    expect(Denomination::isBill(20000))->toBeTrue();
    expect(Denomination::isBill(500))->toBeFalse();
    expect(Denomination::isCoin(500))->toBeTrue();
    expect(Denomination::isCoin(20000))->toBeFalse();
    expect(Denomination::isValid(10000))->toBeTrue();
    expect(Denomination::isValid(999))->toBeFalse();
});

test('Denomination calcula total correctamente desde conteos', function () {
    $counts = [
        '20000' => 2, // $40.000
        '10000' => 3, // $30.000
        '5000' => 4,  // $20.000
        '500' => 10,  // $5.000
    ];

    $total = Denomination::calculateTotal($counts);
    expect($total)->toBe(95000.0);
});

test('Denomination emptyStructure retorna estructura inicializada', function () {
    $structure = Denomination::emptyStructure();
    
    expect($structure)->toHaveKeys(['bills', 'coins']);
    expect(count($structure['bills']))->toBe(5);
    expect(count($structure['coins']))->toBe(6);
    expect($structure['bills']['20000'])->toBe(0);
    expect($structure['coins']['500'])->toBe(0);
});

// ============================================
// MovementType Value Object
// ============================================

test('MovementType tiene tipos correctos', function () {
    expect(MovementType::WITHDRAWAL->value)->toBe('withdrawal');
    expect(MovementType::DEPOSIT->value)->toBe('deposit');
    expect(MovementType::ADJUSTMENT->value)->toBe('adjustment');
});

test('MovementType balanceSign es correcto', function () {
    expect(MovementType::WITHDRAWAL->balanceSign())->toBe(-1);
    expect(MovementType::DEPOSIT->balanceSign())->toBe(1);
    expect(MovementType::ADJUSTMENT->balanceSign())->toBe(-1);
});

test('MovementType identifica tipos que requieren autorización', function () {
    expect(MovementType::WITHDRAWAL->requiresAuthorization())->toBeTrue();
    expect(MovementType::DEPOSIT->requiresAuthorization())->toBeFalse();
    expect(MovementType::ADJUSTMENT->requiresAuthorization())->toBeTrue();
});

// ============================================
// CashRegister Entity
// ============================================

test('se puede crear una caja registradora', function () {
    $register = CashRegister::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Caja 1',
        'code' => 'CAJA-01',
    ]);

    expect($register->id)->not->toBeNull();
    expect($register->is_active)->toBeTrue();
    expect($register->opening_amount_default)->toBe('50000.00');
});

test('CashRegister isAvailable verifica sin sesión abierta', function () {
    $register = CashRegister::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Caja 1',
        'code' => 'CAJA-01',
    ]);

    expect($register->isAvailable())->toBeTrue();

    // Crear sesión abierta
    CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'register_id' => $register->id,
        'session_number' => 'CS-TEST-001',
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    // Recargar relaciones explícitamente
    $register->load('sessions');
    expect($register->isAvailable())->toBeFalse();
    expect($register->isBusy())->toBeTrue();
});

// ============================================
// CashSession extendido
// ============================================

test('CashSession tiene relación con CashRegister', function () {
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
        'session_number' => 'CS-TEST-002',
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    // Cargar relación explícitamente
    $session->load('register');
    expect($session->register)->not->toBeNull();
    expect($session->register->name)->toBe('Caja 1');
});

// ============================================
// CashMovement Entity
// ============================================

test('se puede crear un movimiento de retiro', function () {
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
        'session_number' => 'CS-TEST-003',
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $movement = CashMovement::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'cash_session_id' => $session->id,
        'user_id' => $this->cashier->id,
        'type' => MovementType::WITHDRAWAL,
        'amount' => 30000,
        'reason' => 'Exceso en caja',
        'balance_after' => 20000,
    ]);

    expect($movement->id)->not->toBeNull();
    expect($movement->type)->toBe(MovementType::WITHDRAWAL);
    expect($movement->balanceImpact())->toBe(-30000.0);
});

test('CashMovement depósito tiene impacto positivo', function () {
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
        'session_number' => 'CS-TEST-004',
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $movement = CashMovement::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'cash_session_id' => $session->id,
        'user_id' => $this->cashier->id,
        'type' => MovementType::DEPOSIT,
        'amount' => 20000,
        'reason' => 'Falta cambio',
        'balance_after' => 70000,
    ]);

    expect($movement->balanceImpact())->toBe(20000.0);
});

test('CashMovement authorize registra supervisor', function () {
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
        'session_number' => 'CS-TEST-005',
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    $movement = CashMovement::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'cash_session_id' => $session->id,
        'user_id' => $this->cashier->id,
        'type' => MovementType::WITHDRAWAL,
        'amount' => 100000,
        'reason' => 'Retiro grande',
        'balance_after' => -50000,
    ]);

    expect($movement->isAuthorized())->toBeFalse();

    $movement->authorize($this->manager);

    $movement->refresh();
    expect($movement->isAuthorized())->toBeTrue();
    expect($movement->authorized_by)->toBe($this->manager->id);
});

// ============================================
// CashCount Entity (Arqueos)
// ============================================

test('se puede crear un arqueo con denominaciones', function () {
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
        'session_number' => 'CS-TEST-006',
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
        'expected_amount' => 150000,
        'counted_amount' => 150000,
        'difference' => 0,
        'denominations' => [
            'bills' => [
                '20000' => 3,  // $60.000
                '10000' => 4,  // $40.000
                '5000' => 5,   // $25.000
                '2000' => 5,   // $10.000
                '1000' => 15,  // $15.000
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
        'cash_amount' => 150000,
        'card_amount' => 0,
    ]);

    expect($count->id)->not->toBeNull();
    expect($count->type)->toBe(CashCountType::CLOSING);
    expect($count->isBalanced())->toBeTrue();
    expect($count->hasSurplus())->toBeFalse();
    expect($count->hasShortage())->toBeFalse();
});

test('CashCount recalcula desde denominaciones', function () {
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
        'session_number' => 'CS-TEST-007',
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
        'expected_amount' => 100000,
        'counted_amount' => 0,
        'difference' => 0,
        'denominations' => [
            'bills' => [
                '20000' => 2,  // $40.000
                '10000' => 3,  // $30.000
                '5000' => 4,   // $20.000
                '2000' => 3,   // $6.000
                '1000' => 5,   // $5.000
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

    $total = $count->recalculateFromDenominations();
    
    expect($total)->toBe(101000.0);
    expect((float) $count->counted_amount)->toBe(101000.0);
    expect((float) $count->difference)->toBe(1000.0);
    expect($count->hasSurplus())->toBeTrue();
    expect($count->isBalanced())->toBeFalse();
});

test('CashCount detecta faltante', function () {
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
        'session_number' => 'CS-TEST-008',
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
        'expected_amount' => 100000,
        'counted_amount' => 98500,
        'difference' => -1500,
        'has_discrepancy' => true,
    ]);

    expect($count->hasShortage())->toBeTrue();
    expect($count->hasSurplus())->toBeFalse();
    expect($count->isBalanced())->toBeFalse();
    expect($count->discrepancyPercentage())->toBe(1.5);
});

test('CashCount supervise registra supervisor', function () {
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
        'session_number' => 'CS-TEST-009',
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
        'expected_amount' => 100000,
        'counted_amount' => 98500,
        'difference' => -1500,
        'has_discrepancy' => true,
    ]);

    $count->supervise($this->manager, 'Faltante justificado por error de cambio');

    $count->refresh();
    expect($count->supervised_by)->toBe($this->manager->id);
    expect($count->discrepancy_explanation)->toBe('Faltante justificado por error de cambio');
    expect($count->supervised_at)->not->toBeNull();
});
