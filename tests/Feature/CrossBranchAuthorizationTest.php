<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use App\Shared\Application\TenantContext;

uses(RefreshDatabase::class);

/**
 * Tests de autorización cross-branch (Hallazgo 10).
 * 
 * Valida que la semántica de alcance esté bien definida:
 * - admin, manager: company-wide (ven datos de todas las sucursales)
 * - waiter, cashier, kitchen: branch-wide (limitados a su sucursal)
 * 
 * CompanyScope NUNCA se bypassea (seguridad fundamental multi-tenant).
 */

beforeEach(function () {
    // Crear empresa con 2 sucursales
    $this->company = Company::create([
        'tax_id' => 'CROSS-' . uniqid(),
        'legal_name' => 'Cross-Branch Test Company',
        'trade_name' => 'Cross Test',
    ]);

    $this->branchA = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'BR-A',
        'name' => 'Branch A',
    ]);

    $this->branchB = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'BR-B',
        'name' => 'Branch B',
    ]);

    // Crear usuarios de cada rol en ambas sucursales
    $this->adminA = createCrossUser('admin', $this->company, $this->branchA);
    $this->managerA = createCrossUser('manager', $this->company, $this->branchA);
    $this->waiterA = createCrossUser('waiter', $this->company, $this->branchA);
    $this->waiterB = createCrossUser('waiter', $this->company, $this->branchB);
    $this->cashierA = createCrossUser('cashier', $this->company, $this->branchA);
    $this->cashierB = createCrossUser('cashier', $this->company, $this->branchB);
    $this->kitchenA = createCrossUser('kitchen', $this->company, $this->branchA);

    // Crear pedidos en ambas sucursales
    $this->orderA = createCrossOrder($this->company, $this->branchA, $this->waiterA);
    $this->orderB = createCrossOrder($this->company, $this->branchB, $this->waiterB);
});

function createCrossUser(string $role, Company $company, Branch $branch): User
{
    return User::create([
        'name' => ucfirst($role) . ' ' . uniqid(),
        'email' => "{$role}-" . uniqid() . '@cross.test',
        'password' => bcrypt('password'),
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'role' => $role,
    ]);
}

function createCrossOrder(Company $company, Branch $branch, User $waiter): Order
{
    $tenantContext = app(TenantContext::class);
    $tenantContext->setCompany($company->id, $branch->id, $waiter->id, null, $waiter->role);
    
    return Order::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'waiter_id' => $waiter->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 10000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 10000,
    ]);
}

// ============================================
// ADMIN - company-wide
// ============================================

test('Hallazgo 10: admin puede ver pedidos de su sucursal', function () {
    $tenantContext = app(TenantContext::class);
    $tenantContext->setCompany($this->company->id, $this->branchA->id, $this->adminA->id, null, 'admin');

    $orders = Order::all();
    
    expect($orders)->toHaveCount(2)
        ->and($orders->pluck('id')->toArray())
        ->toContain($this->orderA->id, $this->orderB->id);
});

test('Hallazgo 10: admin puede ver pedidos de OTRA sucursal de su empresa', function () {
    $tenantContext = app(TenantContext::class);
    $tenantContext->setCompany($this->company->id, $this->branchA->id, $this->adminA->id, null, 'admin');

    // Admin intenta ver pedido de branch B explícitamente
    $order = Order::find($this->orderB->id);
    
    expect($order)->not->toBeNull()
        ->and($order->branch_id)->toBe($this->branchB->id);
});

// ============================================
// MANAGER - company-wide
// ============================================

test('Hallazgo 10: manager puede ver pedidos de su sucursal', function () {
    $tenantContext = app(TenantContext::class);
    $tenantContext->setCompany($this->company->id, $this->branchA->id, $this->managerA->id, null, 'manager');

    $orders = Order::all();
    
    expect($orders)->toHaveCount(2);
});

test('Hallazgo 10: manager puede ver pedidos de OTRA sucursal de su empresa', function () {
    $tenantContext = app(TenantContext::class);
    $tenantContext->setCompany($this->company->id, $this->branchA->id, $this->managerA->id, null, 'manager');

    $order = Order::find($this->orderB->id);
    
    expect($order)->not->toBeNull();
});

// ============================================
// WAITER - branch-wide
// ============================================

test('Hallazgo 10: waiter SOLO ve pedidos de su propia sucursal', function () {
    $tenantContext = app(TenantContext::class);
    $tenantContext->setCompany($this->company->id, $this->branchA->id, $this->waiterA->id, null, 'waiter');

    $orders = Order::all();
    
    expect($orders)->toHaveCount(1)
        ->and($orders->first()->id)->toBe($this->orderA->id);
});

test('Hallazgo 10: waiter NO puede ver pedidos de OTRA sucursal', function () {
    $tenantContext = app(TenantContext::class);
    $tenantContext->setCompany($this->company->id, $this->branchA->id, $this->waiterA->id, null, 'waiter');

    // Waiter intenta ver pedido de branch B
    $order = Order::find($this->orderB->id);
    
    expect($order)->toBeNull(); // BranchScope lo filtra
});

// ============================================
// CASHIER - branch-wide
// ============================================

test('Hallazgo 10: cashier SOLO ve pedidos de su propia sucursal', function () {
    $tenantContext = app(TenantContext::class);
    $tenantContext->setCompany($this->company->id, $this->branchA->id, $this->cashierA->id, null, 'cashier');

    $orders = Order::all();
    
    expect($orders)->toHaveCount(1)
        ->and($orders->first()->id)->toBe($this->orderA->id);
});

test('Hallazgo 10: cashier NO puede ver pedidos de OTRA sucursal', function () {
    $tenantContext = app(TenantContext::class);
    $tenantContext->setCompany($this->company->id, $this->branchA->id, $this->cashierA->id, null, 'cashier');

    $order = Order::find($this->orderB->id);
    
    expect($order)->toBeNull();
});

// ============================================
// SEGURIDAD FUNDAMENTAL: CompanyScope NUNCA se bypassea
// ============================================

test('Hallazgo 10: admin NO puede ver pedidos de OTRA empresa (CompanyScope activo)', function () {
    // Crear otra empresa con una sucursal
    $otherCompany = Company::create([
        'tax_id' => 'OTHER-' . uniqid(),
        'legal_name' => 'Other Company',
        'trade_name' => 'Other',
    ]);

    $otherBranch = Branch::create([
        'company_id' => $otherCompany->id,
        'code' => 'OTHER',
        'name' => 'Other Branch',
    ]);

    $otherWaiter = createCrossUser('waiter', $otherCompany, $otherBranch);
    $otherOrder = createCrossOrder($otherCompany, $otherBranch, $otherWaiter);

    // Admin intenta ver pedido de otra empresa
    $tenantContext = app(TenantContext::class);
    $tenantContext->setCompany($this->company->id, $this->branchA->id, $this->adminA->id, null, 'admin');

    $order = Order::find($otherOrder->id);
    
    expect($order)->toBeNull(); // CompanyScope lo bloquea
});

// ============================================
// Helpers del User model
// ============================================

test('Hallazgo 10: User::isCompanyWide() retorna true para admin/manager', function () {
    expect($this->adminA->isCompanyWide())->toBeTrue()
        ->and($this->managerA->isCompanyWide())->toBeTrue()
        ->and($this->waiterA->isCompanyWide())->toBeFalse()
        ->and($this->cashierA->isCompanyWide())->toBeFalse()
        ->and($this->kitchenA->isCompanyWide())->toBeFalse();
});

test('Hallazgo 10: User::isBranchWide() retorna true para waiter/cashier/kitchen', function () {
    expect($this->adminA->isBranchWide())->toBeFalse()
        ->and($this->managerA->isBranchWide())->toBeFalse()
        ->and($this->waiterA->isBranchWide())->toBeTrue()
        ->and($this->cashierA->isBranchWide())->toBeTrue()
        ->and($this->kitchenA->isBranchWide())->toBeTrue();
});
