<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Modules\Cashier\Domain\Entities\CashRegister;
use Modules\Cashier\Domain\Entities\CashMovement;
use Modules\Cashier\Domain\Entities\CashCount;
use Modules\Cashier\Domain\Services\CashMovementService;
use Modules\Cashier\Domain\Services\CashCountService;
use Modules\Cashier\Domain\Services\CashRegisterService;
use Modules\Cashier\Domain\Exceptions\CashierException;
use Modules\Cashier\Domain\ValueObjects\MovementType;
use Modules\Cashier\Domain\ValueObjects\CashCountType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => '76.123.456-7',
        'legal_name' => 'Cashier Services Test SpA',
        'trade_name' => 'Cashier Services Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'CSVC',
        'name' => 'Services Branch',
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

    $this->register = CashRegister::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Caja Principal',
        'code' => 'MAIN-01',
    ]);

    $this->session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'register_id' => $this->register->id,
        'session_number' => 'CS-SVC-001',
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);
});

// ============================================
// CashMovementService - Retiros
// ============================================

test('CashMovementService withdrawal crea retiro válido', function () {
    $service = new CashMovementService();

    $movement = $service->withdrawal(
        $this->session,
        $this->cashier,
        20000,
        'Exceso en caja'
    );

    expect($movement->id)->not->toBeNull();
    expect($movement->type)->toBe(MovementType::WITHDRAWAL);
    expect((float) $movement->amount)->toBe(20000.0);
    expect((float) $movement->balance_after)->toBe(30000.0); // 50k - 20k
});

test('CashMovementService withdrawal grande requiere autorización', function () {
    $service = new CashMovementService();

    expect(fn() => $service->withdrawal(
        $this->session,
        $this->cashier,
        100000, // Sobre el threshold de $50k
        'Retiro grande'
    ))->toThrow(CashierException::class);
});

test('CashMovementService withdrawal grande con autorización funciona', function () {
    $service = new CashMovementService();

    // Primero depositar $100.000 para tener balance suficiente
    // Balance: $50.000 (apertura) + $100.000 (depósito) = $150.000
    $service->deposit($this->session, $this->cashier, 100000, 'Depósito para prueba');

    // Ahora retirar $100.000 con autorización (supera threshold de $50k)
    $movement = $service->withdrawal(
        $this->session,
        $this->cashier,
        100000,
        'Retiro grande autorizado',
        null,
        $this->manager
    );

    expect($movement->isAuthorized())->toBeTrue();
    expect($movement->authorized_by)->toBe($this->manager->id);
    expect((float) $movement->balance_after)->toBe(50000.0); // 150k - 100k
});

test('CashMovementService withdrawal no permite balance negativo', function () {
    $service = new CashMovementService();

    expect(fn() => $service->withdrawal(
        $this->session,
        $this->cashier,
        60000, // Más que la apertura de $50k
        'Retiro imposible'
    ))->toThrow(CashierException::class);
});

// ============================================
// CashMovementService - Depósitos
// ============================================

test('CashMovementService deposit crea depósito válido', function () {
    $service = new CashMovementService();

    $movement = $service->deposit(
        $this->session,
        $this->cashier,
        30000,
        'Falta cambio'
    );

    expect($movement->type)->toBe(MovementType::DEPOSIT);
    expect((float) $movement->balance_after)->toBe(80000.0); // 50k + 30k
});

// ============================================
// CashMovementService - Ajustes
// ============================================

test('CashMovementService adjustment siempre requiere autorización', function () {
    $service = new CashMovementService();

    expect(fn() => $service->adjustment(
        $this->session,
        $this->cashier,
        1000,
        'Error de conteo',
        null // Sin supervisor
    ))->toThrow(CashierException::class);
});

test('CashMovementService adjustment con autorización funciona', function () {
    $service = new CashMovementService();

    $movement = $service->adjustment(
        $this->session,
        $this->cashier,
        500,
        'Corrección de error',
        $this->manager
    );

    expect($movement->type)->toBe(MovementType::ADJUSTMENT);
    expect($movement->isAuthorized())->toBeTrue();
});

// ============================================
// CashMovementService - Validaciones
// ============================================

test('CashMovementService rechaza monto negativo', function () {
    $service = new CashMovementService();

    expect(fn() => $service->withdrawal(
        $this->session,
        $this->cashier,
        -1000,
        'Negativo'
    ))->toThrow(CashierException::class);
});

test('CashMovementService rechaza movimiento en sesión cerrada', function () {
    $this->session->status = CashSessionStatus::CLOSED;
    $this->session->save();

    $service = new CashMovementService();

    expect(fn() => $service->withdrawal(
        $this->session,
        $this->cashier,
        1000,
        'En sesión cerrada'
    ))->toThrow(CashierException::class);
});

test('CashMovementService rechaza si cajero se autoautoriza', function () {
    $service = new CashMovementService();

    expect(fn() => $service->withdrawal(
        $this->session,
        $this->cashier,
        60000,
        'Autoautorización',
        null,
        $this->cashier // Mismo usuario
    ))->toThrow(CashierException::class);
});

// ============================================
// CashMovementService - Summary
// ============================================

test('getSessionSummary retorna resumen correcto', function () {
    $service = new CashMovementService();

    $service->withdrawal($this->session, $this->cashier, 10000, 'Retiro 1');
    $service->deposit($this->session, $this->cashier, 20000, 'Depósito 1');
    $service->withdrawal($this->session, $this->cashier, 5000, 'Retiro 2');

    $summary = $service->getSessionSummary($this->session);

    expect($summary['withdrawals_count'])->toBe(2);
    expect($summary['withdrawals_total'])->toBe(15000.0);
    expect($summary['deposits_count'])->toBe(1);
    expect($summary['deposits_total'])->toBe(20000.0);
    expect($summary['net_impact'])->toBe(5000.0); // +20k -10k -5k
});

// ============================================
// CashCountService - Arqueos
// ============================================

test('CashCountService openingCount crea arqueo de apertura', function () {
    $service = new CashCountService();

    $denominations = [
        'bills' => [
            '20000' => 1,  // $20.000
            '10000' => 2,  // $20.000
            '5000' => 2,   // $10.000
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
    ];

    $count = $service->openingCount($this->session, $this->cashier, $denominations);

    expect($count->id)->not->toBeNull();
    expect($count->type)->toBe(CashCountType::OPENING);
    expect((float) $count->expected_amount)->toBe(50000.0); // Apertura
    expect((float) $count->counted_amount)->toBe(50000.0);
    expect((float) $count->difference)->toBe(0.0);
    expect($count->isBalanced())->toBeTrue();
});

test('CashCountService detecta discrepancia en arqueo', function () {
    // Crear un movimiento para aumentar el balance
    $movementService = new CashMovementService();
    $movementService->deposit($this->session, $this->cashier, 50000, 'Depósito');

    $service = new CashCountService();

    // Contar menos de lo esperado (esperado: 100k, contado: 98k)
    $denominations = [
        'bills' => [
            '20000' => 3,  // $60.000
            '10000' => 2,  // $20.000
            '5000' => 2,   // $10.000
            '2000' => 3,   // $6.000
            '1000' => 2,   // $2.000
        ],
        'coins' => [
            '500' => 0,
            '100' => 0,
            '50' => 0,
            '10' => 0,
            '5' => 0,
            '1' => 0,
        ],
    ];

    $count = $service->closingCount($this->session, $this->cashier, $denominations);

    expect((float) $count->expected_amount)->toBe(100000.0); // 50k + 50k
    expect((float) $count->counted_amount)->toBe(98000.0);
    expect((float) $count->difference)->toBe(-2000.0);
    expect($count->has_discrepancy)->toBeTrue();
    expect($count->hasShortage())->toBeTrue();
});

test('CashCountService valida denominaciones inválidas', function () {
    $service = new CashCountService();

    $invalidDenominations = [
        'bills' => ['99999' => 5], // No es billete válido
        'coins' => [],
    ];

    expect(fn() => $service->openingCount(
        $this->session,
        $this->cashier,
        $invalidDenominations
    ))->toThrow(CashierException::class);
});

test('CashCountService superviseDiscrepancy requiere supervisor diferente', function () {
    $service = new CashCountService();

    // Crear arqueo con discrepancia
    $count = CashCount::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'cash_session_id' => $this->session->id,
        'user_id' => $this->cashier->id,
        'type' => CashCountType::CLOSING,
        'expected_amount' => 100000,
        'counted_amount' => 95000,
        'difference' => -5000,
        'has_discrepancy' => true,
    ]);

    expect(fn() => $service->superviseDiscrepancy(
        $count,
        $this->cashier, // Mismo usuario
        'Justificación'
    ))->toThrow(CashierException::class);
});

test('CashCountService superviseDiscrepancy con supervisor válido funciona', function () {
    $service = new CashCountService();

    $count = CashCount::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'cash_session_id' => $this->session->id,
        'user_id' => $this->cashier->id,
        'type' => CashCountType::CLOSING,
        'expected_amount' => 100000,
        'counted_amount' => 95000,
        'difference' => -5000,
        'has_discrepancy' => true,
    ]);

    $supervised = $service->superviseDiscrepancy(
        $count,
        $this->manager,
        'Faltante por error de cambio en hora pico'
    );

    expect($supervised->supervised_by)->toBe($this->manager->id);
    expect($supervised->discrepancy_explanation)->toBe('Faltante por error de cambio en hora pico');
});

// ============================================
// CashRegisterService
// ============================================

test('CashRegisterService create valida código único', function () {
    $service = new CashRegisterService();

    expect(fn() => $service->create(
        $this->branch,
        'Caja Duplicada',
        'MAIN-01' // Ya existe
    ))->toThrow(CashierException::class);
});

test('CashRegisterService create funciona con código único', function () {
    $service = new CashRegisterService();

    $register = $service->create(
        $this->branch,
        'Caja 2',
        'CAJA-02',
        30000,
        400000
    );

    expect($register->id)->not->toBeNull();
    expect($register->code)->toBe('CAJA-02');
    expect((float) $register->opening_amount_default)->toBe(30000.0);
});

test('CashRegisterService getAvailableRegisters retorna solo cajas sin sesión', function () {
    $service = new CashRegisterService();

    // Crear caja adicional sin sesión
    $service->create($this->branch, 'Caja 2', 'CAJA-02');

    $available = $service->getAvailableRegisters(
        $this->company->id,
        $this->branch->id
    );

    // Solo CAJA-02 está disponible (MAIN-01 tiene sesión abierta)
    expect($available->count())->toBe(1);
    expect($available->first()->code)->toBe('CAJA-02');
});

test('CashRegisterService toggleActive rechaza desactivar caja ocupada', function () {
    $service = new CashRegisterService();

    expect(fn() => $service->toggleActive($this->register, false))
        ->toThrow(CashierException::class);
});
