<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Super-admin (empresa matriz)
    $this->superCompany = Company::create([
        'tax_id' => 'SUPER-' . uniqid(),
        'legal_name' => 'Super Admin Corp',
        'trade_name' => 'Super Admin',
    ]);

    $this->superAdmin = User::withoutGlobalScopes()->create([
        'name' => 'Super Admin',
        'email' => 'super-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->superCompany->id,
        'branch_id' => null,
        'role' => 'super_admin',
        'locale' => 'es-CL',
        'is_active' => true,
    ]);

    // Empresa A
    $this->companyA = Company::create([
        'tax_id' => 'COMP-A-' . uniqid(),
        'legal_name' => 'Company A',
        'trade_name' => 'Restaurant A',
    ]);

    $this->branchA = Branch::create([
        'company_id' => $this->companyA->id,
        'code' => 'BR-A',
        'name' => 'Branch A',
    ]);

    $this->adminA = User::create([
        'name' => 'Admin A',
        'email' => 'admin-a-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'role' => 'admin',
        'locale' => 'es-CL',
        'is_active' => true,
    ]);

    // Empresa B
    $this->companyB = Company::create([
        'tax_id' => 'COMP-B-' . uniqid(),
        'legal_name' => 'Company B',
        'trade_name' => 'Restaurant B',
    ]);

    $this->branchB = Branch::create([
        'company_id' => $this->companyB->id,
        'code' => 'BR-B',
        'name' => 'Branch B',
    ]);

    $this->adminB = User::create([
        'name' => 'Admin B',
        'email' => 'admin-b-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'role' => 'admin',
        'locale' => 'es-CL',
        'is_active' => true,
    ]);
});

// ============================================
// POST /api/v1/companies
// ============================================

test('super_admin puede crear empresa', function () {
    $token = loginAs($this->superAdmin);

    $response = $this->withHeaders(authHeaders($token))
        ->postJson('/api/v1/companies', [
            'tax_id' => 'NEW-' . uniqid(),
            'legal_name' => 'New Company',
            'trade_name' => 'New Restaurant',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.legal_name', 'New Company')
        ->assertJsonPath('data.trade_name', 'New Restaurant');
});

test('admin NO puede crear empresa', function () {
    $token = loginAs($this->adminA);

    $response = $this->withHeaders(authHeaders($token))
        ->postJson('/api/v1/companies', [
            'tax_id' => 'NEW-' . uniqid(),
            'legal_name' => 'New Company',
            'trade_name' => 'New Restaurant',
        ]);

    $response->assertStatus(403);
});

test('empresa nueva tiene todas las capabilities habilitadas', function () {
    $token = loginAs($this->superAdmin);

    $response = $this->withHeaders(authHeaders($token))
        ->postJson('/api/v1/companies', [
            'tax_id' => 'NEW-' . uniqid(),
            'legal_name' => 'New Company',
            'trade_name' => 'New Restaurant',
            'enable_all_capabilities' => true,
        ]);

    $response->assertStatus(201);
    
    $companyUuid = $response->json('data.uuid');
    $capabilities = $response->json('data.capabilities');
    
    expect($capabilities)->toHaveCount(8); // 8 capabilities definidas
    expect(collect($capabilities)->where('is_enabled', true)->count())->toBe(8);
});

// ============================================
// GET /api/v1/companies
// ============================================

test('super_admin ve todas las empresas', function () {
    $token = loginAs($this->superAdmin);

    $response = $this->withHeaders(authHeaders($token))
        ->getJson('/api/v1/companies');

    $response->assertStatus(200);
    
    $companies = $response->json('data');
    expect($companies)->toHaveCount(3); // superCompany + companyA + companyB
});

test('admin solo ve su propia empresa', function () {
    $token = loginAs($this->adminA);

    $response = $this->withHeaders(authHeaders($token))
        ->getJson('/api/v1/companies');

    $response->assertStatus(200);
    
    $companies = $response->json('data');
    expect($companies)->toHaveCount(1);
    expect($companies[0]['uuid'])->toBe($this->companyA->uuid);
});

// ============================================
// GET /api/v1/companies/{uuid}
// ============================================

test('admin puede ver su propia empresa', function () {
    $token = loginAs($this->adminA);

    $response = $this->withHeaders(authHeaders($token))
        ->getJson('/api/v1/companies/' . $this->companyA->uuid);

    $response->assertStatus(200)
        ->assertJsonPath('data.uuid', $this->companyA->uuid);
});

test('admin NO puede ver empresa de otra compañía', function () {
    $token = loginAs($this->adminA);

    $response = $this->withHeaders(authHeaders($token))
        ->getJson('/api/v1/companies/' . $this->companyB->uuid);

    $response->assertStatus(403);
});

test('super_admin puede ver cualquier empresa', function () {
    $token = loginAs($this->superAdmin);

    $response = $this->withHeaders(authHeaders($token))
        ->getJson('/api/v1/companies/' . $this->companyB->uuid);

    $response->assertStatus(200)
        ->assertJsonPath('data.uuid', $this->companyB->uuid);
});

// ============================================
// PUT /api/v1/companies/{uuid}
// ============================================

test('admin puede actualizar su propia empresa', function () {
    $token = loginAs($this->adminA);

    $response = $this->withHeaders(authHeaders($token))
        ->putJson('/api/v1/companies/' . $this->companyA->uuid, [
            'trade_name' => 'Updated Restaurant A',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.trade_name', 'Updated Restaurant A');
});

test('admin NO puede actualizar empresa de otra compañía', function () {
    $token = loginAs($this->adminA);

    $response = $this->withHeaders(authHeaders($token))
        ->putJson('/api/v1/companies/' . $this->companyB->uuid, [
            'trade_name' => 'Hacked',
        ]);

    $response->assertStatus(403);
});

// ============================================
// DELETE /api/v1/companies/{uuid}
// ============================================

test('admin puede eliminar su propia empresa (soft delete)', function () {
    $token = loginAs($this->adminA);

    $response = $this->withHeaders(authHeaders($token))
        ->deleteJson('/api/v1/companies/' . $this->companyA->uuid);

    $response->assertStatus(200);
    
    // Verificar que fue soft delete
    $this->assertSoftDeleted('companies', ['uuid' => $this->companyA->uuid]);
});

test('admin NO puede eliminar empresa de otra compañía', function () {
    $token = loginAs($this->adminA);

    $response = $this->withHeaders(authHeaders($token))
        ->deleteJson('/api/v1/companies/' . $this->companyB->uuid);

    $response->assertStatus(403);
});

// ============================================
// GET /api/v1/companies/{uuid}/capabilities
// ============================================

test('admin puede ver capabilities de su empresa', function () {
    // Crear algunas capabilities
    \Modules\Companies\Domain\Entities\CompanyCapability::create([
        'company_id' => $this->companyA->id,
        'capability_key' => 'can_split_bills',
        'is_enabled' => true,
    ]);

    $token = loginAs($this->adminA);

    $response = $this->withHeaders(authHeaders($token))
        ->getJson('/api/v1/companies/' . $this->companyA->uuid . '/capabilities');

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => [['key', 'description', 'is_enabled', 'settings']]]);
});

test('admin NO puede ver capabilities de otra empresa', function () {
    $token = loginAs($this->adminA);

    $response = $this->withHeaders(authHeaders($token))
        ->getJson('/api/v1/companies/' . $this->companyB->uuid . '/capabilities');

    $response->assertStatus(403);
});

// ============================================
// PUT /api/v1/companies/{uuid}/capabilities
// ============================================

test('admin puede actualizar capabilities de su empresa', function () {
    $token = loginAs($this->adminA);

    $response = $this->withHeaders(authHeaders($token))
        ->putJson('/api/v1/companies/' . $this->companyA->uuid . '/capabilities', [
            'capabilities' => [
                ['key' => 'can_split_bills', 'is_enabled' => true, 'settings' => ['max_parts' => 5]],
                ['key' => 'can_accept_tips', 'is_enabled' => false],
            ],
        ]);

    $response->assertStatus(200);
    
    // Verificar que se actualizaron
    $this->assertDatabaseHas('company_capabilities', [
        'company_id' => $this->companyA->id,
        'capability_key' => 'can_split_bills',
        'is_enabled' => true,
    ]);
});

test('admin NO puede actualizar capabilities de otra empresa', function () {
    $token = loginAs($this->adminA);

    $response = $this->withHeaders(authHeaders($token))
        ->putJson('/api/v1/companies/' . $this->companyB->uuid . '/capabilities', [
            'capabilities' => [
                ['key' => 'can_split_bills', 'is_enabled' => true],
            ],
        ]);

    $response->assertStatus(403);
});

