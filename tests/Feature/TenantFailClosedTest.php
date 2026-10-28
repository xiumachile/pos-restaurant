<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Exceptions\TenantContextNotSetException;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // ✅ Limpieza explícita para garantizar aislamiento absoluto en este test
    // Evita fallos por acumulación de registros de otros tests (fuga de estado)
    User::withoutGlobalScopes()->truncate();
    Order::withoutGlobalScopes()->truncate();
    Branch::withoutGlobalScopes()->truncate();
    Company::withoutGlobalScopes()->truncate();

    $this->companyA = Company::create([
        'tax_id' => '76.111.111-1',
        'legal_name' => 'Company A SpA',
        'trade_name' => 'Company A',
    ]);

    $this->companyB = Company::create([
        'tax_id' => '76.222.222-2',
        'legal_name' => 'Company B SpA',
        'trade_name' => 'Company B',
    ]);

    $this->branchA = Branch::create([
        'company_id' => $this->companyA->id,
        'code' => 'BRA',
        'name' => 'Branch A',
    ]);

    $this->branchB = Branch::create([
        'company_id' => $this->companyB->id,
        'code' => 'BRB',
        'name' => 'Branch B',
    ]);

    $this->userA = User::create([
        'name' => 'User A',
        'email' => 'usera@test.com',
        'password' => 'password',
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'role' => 'cashier',
        'is_active' => true,
    ]);

    $this->userB = User::create([
        'name' => 'User B',
        'email' => 'userb@test.com',
        'password' => 'password',
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'role' => 'cashier',
        'is_active' => true,
    ]);

    $this->tenantContext = app(TenantContext::class);
});

// ============================================
// Aislamiento entre empresas (CompanyScope)
// ============================================

test('User de empresa A no puede ver usuarios de empresa B', function () {
    $this->tenantContext->setCompany(
        companyId: $this->companyA->id,
        branchId: $this->branchA->id,
        userId: $this->userA->id,
        locale: 'es-CL',
        role: 'cashier',
        terminalId: null
    );

    $users = User::all();

    expect($users->count())->toBe(1);
    expect($users->first()->id)->toBe($this->userA->id);
});

test('User de empresa B solo ve sus propios usuarios', function () {
    $this->tenantContext->setCompany(
        companyId: $this->companyB->id,
        branchId: $this->branchB->id,
        userId: $this->userB->id,
        locale: 'es-CL',
        role: 'cashier',
        terminalId: null
    );

    $users = User::all();

    expect($users->count())->toBe(1);
    expect($users->first()->id)->toBe($this->userB->id);
});

// ============================================
// Fail-closed sin contexto (sólo en HTTP simulado)
// ============================================

test('CompanyScope permite queries en tests (runningUnitTests)', function () {
    $this->tenantContext->clear();

    $users = User::all();
    expect($users->count())->toBe(2); // Sin filtro, solo los 2 del beforeEach
});

test('withoutGlobalScopes permite acceder a todos los datos', function () {
    $this->tenantContext->setCompany(
        companyId: $this->companyA->id,
        branchId: $this->branchA->id,
        userId: $this->userA->id,
        locale: 'es-CL',
        role: 'cashier',
        terminalId: null
    );

    expect(User::count())->toBe(1);
    expect(User::withoutGlobalScopes()->count())->toBe(2);
});

// ============================================
// Aislamiento por sucursal (BranchScope)
// ============================================

test('BranchScope aísla por branch_id cuando hay contexto', function () {
    $branchA2 = Branch::create([
        'company_id' => $this->companyA->id,
        'code' => 'BRA2',
        'name' => 'Branch A2',
    ]);

    $waiterA = User::create([
        'name' => 'Waiter A',
        'email' => 'waitera@test.com',
        'password' => 'password',
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'role' => 'waiter',
        'is_active' => true,
    ]);

    $waiterA2 = User::create([
        'name' => 'Waiter A2',
        'email' => 'waitera2@test.com',
        'password' => 'password',
        'company_id' => $this->companyA->id,
        'branch_id' => $branchA2->id,
        'role' => 'waiter',
        'is_active' => true,
    ]);

    Order::withoutGlobalScopes()->create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'waiter_id' => $waiterA->id,
        'order_number' => 'ORD-A-1',
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'total' => 1190,
    ]);

    Order::withoutGlobalScopes()->create([
        'company_id' => $this->companyA->id,
        'branch_id' => $branchA2->id,
        'waiter_id' => $waiterA2->id,
        'order_number' => 'ORD-A2-1',
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'subtotal' => 2000,
        'tax_amount' => 380,
        'total' => 2380,
    ]);

    $this->tenantContext->setCompany(
        companyId: $this->companyA->id,
        branchId: $this->branchA->id,
        userId: $waiterA->id,
        locale: 'es-CL',
        role: 'waiter',
        terminalId: null
    );

    $orders = Order::all();

    expect($orders->count())->toBe(1);
    expect($orders->first()->order_number)->toBe('ORD-A-1');
});

// ============================================
// Excepción TenantContextNotSetException
// ============================================

test('TenantContextNotSetException contiene información del modelo', function () {
    $exception = new TenantContextNotSetException(
        modelClass: 'Modules\\Orders\\Domain\\Entities\\Order'
    );

    expect($exception->getMessage())->toContain('Order');
    expect($exception->getModelClass())->toBe('Modules\\Orders\\Domain\\Entities\\Order');
});

test('TenantContextNotSetException tiene mensaje por defecto descriptivo', function () {
    $exception = new TenantContextNotSetException();

    expect($exception->getMessage())->toContain('TenantContext');
    expect($exception->getMessage())->toContain('cross-tenant');
});
