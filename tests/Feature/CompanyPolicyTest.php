<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Empresa A
    $this->companyA = Company::create([
        'tax_id' => 'POL-A-' . uniqid(),
        'legal_name' => 'Policy Test Company A',
        'trade_name' => 'Company A',
    ]);

    // Empresa B (ajena)
    $this->companyB = Company::create([
        'tax_id' => 'POL-B-' . uniqid(),
        'legal_name' => 'Policy Test Company B',
        'trade_name' => 'Company B',
    ]);

    // Usuarios de empresa A con diferentes roles
    $this->superAdmin = User::create([
        'name' => 'Super Admin',
        'email' => 'super-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyA->id,
        'role' => 'super_admin',
    ]);

    $this->adminA = User::create([
        'name' => 'Admin A',
        'email' => 'admin-a-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyA->id,
        'role' => 'admin',
    ]);

    $this->managerA = User::create([
        'name' => 'Manager A',
        'email' => 'manager-a-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyA->id,
        'role' => 'manager',
    ]);

    $this->waiterA = User::create([
        'name' => 'Waiter A',
        'email' => 'waiter-a-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyA->id,
        'role' => 'waiter',
    ]);

    // Admin de empresa B
    $this->adminB = User::create([
        'name' => 'Admin B',
        'email' => 'admin-b-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyB->id,
        'role' => 'admin',
    ]);
});

function policyHeaders(string $token): array
{
    return [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ];
}

// ============================================
// SUPER_ADMIN: acceso total a cualquier empresa
// ============================================

test('super_admin puede ver cualquier empresa', function () {
    $token = JWTAuth::fromUser($this->superAdmin);

    // Puede ver su propia empresa
    $response = $this->withHeaders(policyHeaders($token))
        ->getJson("/api/v1/companies/{$this->companyA->uuid}");
    $response->assertOk();

    // Puede ver empresa ajena (super_admin tiene acceso total)
    $response = $this->withHeaders(policyHeaders($token))
        ->getJson("/api/v1/companies/{$this->companyB->uuid}");
    $response->assertOk();
});

test('super_admin puede actualizar cualquier empresa', function () {
    $token = JWTAuth::fromUser($this->superAdmin);

    $response = $this->withHeaders(policyHeaders($token))
        ->putJson("/api/v1/companies/{$this->companyB->uuid}", [
            'legal_name' => 'Updated by Super Admin',
            'trade_name' => 'Company B Updated',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.legal_name', 'Updated by Super Admin');
});

test('super_admin puede actualizar capabilities de cualquier empresa', function () {
    $token = JWTAuth::fromUser($this->superAdmin);

    $response = $this->withHeaders(policyHeaders($token))
        ->putJson("/api/v1/companies/{$this->companyB->uuid}/capabilities", [
            'capabilities' => [
                ['key' => 'can_split_bills', 'is_enabled' => false],
            ],
        ]);

    $response->assertOk();
});

// ============================================
// ADMIN: solo puede operar sobre SU empresa
// ============================================

test('admin puede ver su propia empresa', function () {
    $token = JWTAuth::fromUser($this->adminA);

    $response = $this->withHeaders(policyHeaders($token))
        ->getJson("/api/v1/companies/{$this->companyA->uuid}");

    $response->assertOk()
        ->assertJsonPath('data.uuid', $this->companyA->uuid);
});

test('admin NO puede ver empresa ajena (403)', function () {
    $token = JWTAuth::fromUser($this->adminA);

    $response = $this->withHeaders(policyHeaders($token))
        ->getJson("/api/v1/companies/{$this->companyB->uuid}");

    $response->assertStatus(403);
});

test('admin puede actualizar su propia empresa', function () {
    $token = JWTAuth::fromUser($this->adminA);

    $response = $this->withHeaders(policyHeaders($token))
        ->putJson("/api/v1/companies/{$this->companyA->uuid}", [
            'legal_name' => 'Updated by Admin A',
            'trade_name' => 'Company A Updated',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.legal_name', 'Updated by Admin A');
});

test('admin NO puede actualizar empresa ajena (403)', function () {
    $token = JWTAuth::fromUser($this->adminA);

    $response = $this->withHeaders(policyHeaders($token))
        ->putJson("/api/v1/companies/{$this->companyB->uuid}", [
            'legal_name' => 'Should fail',
            'trade_name' => 'Should fail',
        ]);

    $response->assertStatus(403);
});

test('admin puede ver capabilities de su propia empresa', function () {
    $token = JWTAuth::fromUser($this->adminA);

    $response = $this->withHeaders(policyHeaders($token))
        ->getJson("/api/v1/companies/{$this->companyA->uuid}/capabilities");

    $response->assertOk()
        ->assertJsonStructure(['data']);
});

test('admin NO puede ver capabilities de empresa ajena (403)', function () {
    $token = JWTAuth::fromUser($this->adminA);

    $response = $this->withHeaders(policyHeaders($token))
        ->getJson("/api/v1/companies/{$this->companyB->uuid}/capabilities");

    $response->assertStatus(403);
});

test('admin puede actualizar capabilities de su propia empresa', function () {
    $token = JWTAuth::fromUser($this->adminA);

    $response = $this->withHeaders(policyHeaders($token))
        ->putJson("/api/v1/companies/{$this->companyA->uuid}/capabilities", [
            'capabilities' => [
                ['key' => 'can_split_bills', 'is_enabled' => true],
            ],
        ]);

    $response->assertOk();
});

test('admin NO puede actualizar capabilities de empresa ajena (403)', function () {
    $token = JWTAuth::fromUser($this->adminA);

    $response = $this->withHeaders(policyHeaders($token))
        ->putJson("/api/v1/companies/{$this->companyB->uuid}/capabilities", [
            'capabilities' => [
                ['key' => 'can_split_bills', 'is_enabled' => false],
            ],
        ]);

    $response->assertStatus(403);
});

// ============================================
// OTROS ROLES: sin acceso a operaciones de empresa
// ============================================

test('manager NO puede actualizar empresa (403)', function () {
    $token = JWTAuth::fromUser($this->managerA);

    $response = $this->withHeaders(policyHeaders($token))
        ->putJson("/api/v1/companies/{$this->companyA->uuid}", [
            'legal_name' => 'Should fail',
            'trade_name' => 'Should fail',
        ]);

    // Manager no tiene acceso ni siquiera a su propia empresa (es admin-only)
    $response->assertStatus(403);
});

test('waiter NO puede ver detalle de empresa (403)', function () {
    $token = JWTAuth::fromUser($this->waiterA);

    $response = $this->withHeaders(policyHeaders($token))
        ->getJson("/api/v1/companies/{$this->companyA->uuid}");

    $response->assertStatus(403);
});

test('admin B NO puede actualizar empresa A (cross-tenant, 403)', function () {
    $token = JWTAuth::fromUser($this->adminB);

    $response = $this->withHeaders(policyHeaders($token))
        ->putJson("/api/v1/companies/{$this->companyA->uuid}", [
            'legal_name' => 'Cross-tenant attack',
            'trade_name' => 'Should fail',
        ]);

    // Defensa en profundidad: incluso admin no puede tocar empresa ajena
    $response->assertStatus(403);
});
