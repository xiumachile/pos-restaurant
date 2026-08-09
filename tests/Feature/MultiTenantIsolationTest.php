<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use App\Shared\Application\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Crear dos empresas aisladas
    $this->companyA = Company::create([
        'tax_id' => 'EMP-A-001',
        'legal_name' => 'Empresa A',
        'trade_name' => 'Restaurante A',
    ]);

    $this->companyB = Company::create([
        'tax_id' => 'EMP-B-002',
        'legal_name' => 'Empresa B',
        'trade_name' => 'Restaurante B',
    ]);

    // Crear sucursales
    $this->branchA = Branch::create([
        'company_id' => $this->companyA->id,
        'code' => 'A-001',
        'name' => 'Sucursal A',
    ]);

    $this->branchB = Branch::create([
        'company_id' => $this->companyB->id,
        'code' => 'B-001',
        'name' => 'Sucursal B',
    ]);

    // Crear usuarios
    $this->userA = User::create([
        'name' => 'Usuario A',
        'email' => 'user-a@test.cl',
        'password' => 'password',
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'role' => 'admin',
    ]);

    $this->userB = User::create([
        'name' => 'Usuario B',
        'email' => 'user-b@test.cl',
        'password' => 'password',
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'role' => 'admin',
    ]);
});

test('uuid se genera automáticamente en todos los modelos', function () {
    expect($this->companyA->uuid)->not->toBeNull();
    expect($this->branchA->uuid)->not->toBeNull();
    expect($this->userA->uuid)->not->toBeNull();
});

test('usuario A solo ve sucursales de su empresa', function () {
    // Autenticar como usuario A
    $this->actingAs($this->userA);

    // Limpiar contexto y establecer desde usuario
    $tenantContext = app(TenantContext::class);
    $tenantContext->setCompany($this->userA->company_id);

    $branches = Branch::all();

    expect($branches)->toHaveCount(1);
    expect($branches->first()->name)->toBe('Sucursal A');
});

test('usuario B no puede ver sucursales de la empresa A', function () {
    $this->actingAs($this->userB);

    $tenantContext = app(TenantContext::class);
    $tenantContext->setCompany($this->userB->company_id);

    $branches = Branch::all();

    expect($branches)->toHaveCount(1);
    expect($branches->first()->name)->toBe('Sucursal B');
    expect($branches->first()->company_id)->toBe($this->companyB->id);
});

test('branch se asigna automáticamente al crear con usuario autenticado', function () {
    $this->actingAs($this->userA);

    $tenantContext = app(TenantContext::class);
    $tenantContext->setCompany($this->userA->company_id);
    $tenantContext->setBranch($this->userA->branch_id);

    $newBranch = Branch::create([
        'code' => 'A-002',
        'name' => 'Nueva Sucursal A',
    ]);

    expect($newBranch->company_id)->toBe($this->companyA->id);
});

test('company es la raíz del tenant y no tiene aislamiento', function () {
    // Company NO usa BelongsToTenant, debe ver todas
    $companies = Company::all();
    expect($companies->count())->toBeGreaterThanOrEqual(2);
});
