<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Identity\Domain\Services\AuthenticationService;
use Modules\Identity\Domain\Exceptions\InvalidCredentialsException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => '76.123.456-7',
        'legal_name' => 'Login PIN Test SpA',
        'trade_name' => 'Login PIN Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'PIN',
        'name' => 'PIN Test Branch',
    ]);

    // Crear 3 empleados activos en la misma sucursal
    $this->cashier1 = User::create([
        'name' => 'Juan Pérez',
        'email' => 'juan@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
        'is_active' => true,
    ]);
    $this->cashier1->setPosPin('1234');
    $this->cashier1->save();

    $this->cashier2 = User::create([
        'name' => 'María González',
        'email' => 'maria@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
        'is_active' => true,
    ]);
    $this->cashier2->setPosPin('5678');
    $this->cashier2->save();

    $this->cashier3 = User::create([
        'name' => 'Pedro Ramírez',
        'email' => 'pedro@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
        'is_active' => true,
    ]);
    $this->cashier3->setPosPin('9012');
    $this->cashier3->save();

    // Empleado inactivo (no debe poder loguearse)
    $this->inactiveUser = User::create([
        'name' => 'Ana López',
        'email' => 'ana@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
        'is_active' => false,
    ]);
    $this->inactiveUser->setPosPin('3456');
    $this->inactiveUser->save();

    // Empleado sin PIN configurado
    $this->noPinUser = User::create([
        'name' => 'Carlos Soto',
        'email' => 'carlos@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
        'is_active' => true,
    ]);

    $this->authService = new AuthenticationService();
});

// ============================================
// Login exitoso con múltiples usuarios
// ============================================

test('loginWithPin autentica al primer cajero de la sucursal', function () {
    $result = $this->authService->loginWithPin($this->branch->id, '1234');

    expect($result)->toHaveKeys(['access_token', 'user']);
    expect($result['user']['uuid'])->toBe($this->cashier1->uuid);
    expect($result['user']['name'])->toBe('Juan Pérez');
    expect($result['token_type'])->toBe('bearer');
});

test('loginWithPin autentica al segundo cajero de la sucursal', function () {
    $result = $this->authService->loginWithPin($this->branch->id, '5678');

    expect($result['user']['uuid'])->toBe($this->cashier2->uuid);
    expect($result['user']['name'])->toBe('María González');
});

test('loginWithPin autentica al tercer empleado (manager)', function () {
    $result = $this->authService->loginWithPin($this->branch->id, '9012');

    expect($result['user']['uuid'])->toBe($this->cashier3->uuid);
    expect($result['user']['name'])->toBe('Pedro Ramírez');
    expect($result['user']['role'])->toBe('manager');
});

// ============================================
// Casos de error
// ============================================

test('loginWithPin rechaza PIN incorrecto para cualquier cajero', function () {
    expect(fn() => $this->authService->loginWithPin($this->branch->id, '9999'))
        ->toThrow(InvalidCredentialsException::class);
});

test('loginWithPin rechaza PIN de empleado inactivo', function () {
    // PIN de Ana (inactiva) no debe funcionar
    expect(fn() => $this->authService->loginWithPin($this->branch->id, '3456'))
        ->toThrow(InvalidCredentialsException::class);
});

test('loginWithPin ignora usuarios sin pos_pin configurado', function () {
    // Carlos no tiene PIN, no puede loguearse
    expect(fn() => $this->authService->loginWithPin($this->branch->id, '0000'))
        ->toThrow(InvalidCredentialsException::class);
});

test('loginWithPin rechaza PIN vacío', function () {
    expect(fn() => $this->authService->loginWithPin($this->branch->id, ''))
        ->toThrow(InvalidCredentialsException::class);
});

test('loginWithPin rechaza branch_id inexistente', function () {
    expect(fn() => $this->authService->loginWithPin(999999, '1234'))
        ->toThrow(InvalidCredentialsException::class);
});

// ============================================
// Aislamiento multi-tenant
// ============================================

test('loginWithPin no autentica con PIN de otra sucursal', function () {
    // Crear otra sucursal
    $otherBranch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'OTHER',
        'name' => 'Otra Sucursal',
    ]);

    // Crear usuario en otra sucursal con PIN diferente
    $otherUser = User::create([
        'name' => 'Otro Usuario',
        'email' => 'otro@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $otherBranch->id,
        'role' => 'cashier',
        'is_active' => true,
    ]);
    $otherUser->setPosPin('1111');
    $otherUser->save();

    // Intentar usar el PIN de otra sucursal en esta sucursal
    expect(fn() => $this->authService->loginWithPin($this->branch->id, '1111'))
        ->toThrow(InvalidCredentialsException::class);
});

// ============================================
// Estructura de respuesta
// ============================================

test('loginWithPin retorna estructura completa con token JWT', function () {
    $result = $this->authService->loginWithPin($this->branch->id, '1234');

    expect($result)->toHaveKeys([
        'access_token',
        'token_type',
        'expires_in',
        'user',
    ]);

    expect($result['user'])->toHaveKeys([
        'uuid',
        'name',
        'email',
        'role',
        'locale',
        'company_id',
        'branch_id',
        'is_active',
    ]);

    expect($result['token_type'])->toBe('bearer');
    expect($result['expires_in'])->toBeGreaterThan(0);
});
