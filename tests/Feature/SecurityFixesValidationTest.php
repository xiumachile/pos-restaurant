<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Catalog\Application\Services\CatalogExportService;
use Modules\Payments\Application\Services\PaymentsExportService;
use Modules\Audit\Domain\Entities\AuditLog;

uses(RefreshDatabase::class);

/**
 * VALIDACIÓN DE FIXES P0: Seguridad cross-tenant
 */
beforeEach(function () {
    // Tenant A
    $this->companyA = Company::create([
        'tax_id' => 'FIX-A-' . uniqid(),
        'legal_name' => 'Fix Test A',
        'trade_name' => 'Fix A',
    ]);
    $this->branchA = Branch::create([
        'company_id' => $this->companyA->id,
        'code' => 'FIXA',
        'name' => 'Branch A',
    ]);
    $this->userA = User::create([
        'name' => 'User A',
        'email' => 'fix-a-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'role' => 'cashier',
    ]);

    // Tenant B
    $this->companyB = Company::create([
        'tax_id' => 'FIX-B-' . uniqid(),
        'legal_name' => 'Fix Test B',
        'trade_name' => 'Fix B',
    ]);
    $this->branchB = Branch::create([
        'company_id' => $this->companyB->id,
        'code' => 'FIXB',
        'name' => 'Branch B',
    ]);
    $this->userB = User::create([
        'name' => 'User B',
        'email' => 'fix-b-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'role' => 'cashier',
    ]);
});

test('FIX P0: CatalogExportService filtra por company_id', function () {
    // Crear categorías para ambos tenants
    \Modules\Catalog\Domain\Entities\Category::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'name_translations' => ['es' => 'Cat A'],
    ]);
    \Modules\Catalog\Domain\Entities\Category::create([
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'name_translations' => ['es' => 'Cat B'],
    ]);

    // Tenant B intenta exportar
    $this->actingAs($this->userB, 'api');
    $exportService = app(CatalogExportService::class);
    $categories = $exportService->getChangedCategories($this->branchB->id, null);
    
    // Solo debe ver categorías de B
    expect($categories->count())->toBe(1, 'CatalogExportService filtra correctamente');
});

test('FIX P0: PaymentsExportService filtra por company_id', function () {
    // Crear payment methods para ambos tenants
    \Modules\Payments\Domain\Entities\PaymentMethod::create([
        'company_id' => $this->companyA->id,
        'code' => 'cash-a',
        'name_translations' => ['es' => 'Cash A'],
        'type' => 'cash',
        'is_active' => true,
    ]);
    \Modules\Payments\Domain\Entities\PaymentMethod::create([
        'company_id' => $this->companyB->id,
        'code' => 'cash-b',
        'name_translations' => ['es' => 'Cash B'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    // Tenant B intenta exportar
    $this->actingAs($this->userB, 'api');
    $exportService = app(PaymentsExportService::class);
    $methods = $exportService->getChangedPaymentMethods($this->branchB->id, null);
    
    // Solo debe ver métodos de B
    expect($methods->count())->toBe(1, 'PaymentsExportService filtra correctamente');
});

test('FIX P0: AuditLog filtra por company_id (BelongsToTenant)', function () {
    // Crear audit logs para ambos tenants
    AuditLog::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->userA->id,
        'action' => 'test_action',
        'occurred_at' => now(),
    ]);
    AuditLog::create([
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'user_id' => $this->userB->id,
        'action' => 'test_action',
        'occurred_at' => now(),
    ]);

    // Tenant B consulta logs
    $this->actingAs($this->userB, 'api');
    $logs = AuditLog::all();
    
    // Solo debe ver logs de B
    expect($logs->count())->toBe(1, 'AuditLog filtra correctamente')
        ->and($logs->first()->company_id)->toBe($this->companyB->id);
});
