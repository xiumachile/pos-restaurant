<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Tenant A
    $this->companyA = Company::create([
        'tax_id' => 'AUTH-A-' . uniqid(),
        'legal_name' => 'Auth Test Company A',
        'trade_name' => 'Auth Test A',
    ]);

    $this->branchA = Branch::create([
        'company_id' => $this->companyA->id,
        'code' => 'AUTH-A',
        'name' => 'Auth Branch A',
    ]);

    $this->userA = User::create([
        'name' => 'Test User A',
        'email' => 'user-a-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'pos_pin_hash' => Hash::make('1234'),
        'role' => 'cashier',
        'locale' => 'es-CL',
        'is_active' => true,
    ]);

    // Usuario inactivo de empresa A
    $this->inactiveUserA = User::create([
        'name' => 'Inactive User A',
        'email' => 'inactive-a-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'pos_pin_hash' => Hash::make('5678'),
        'role' => 'cashier',
        'locale' => 'es-CL',
        'is_active' => false,
    ]);

    // Tenant B
    $this->companyB = Company::create([
        'tax_id' => 'AUTH-B-' . uniqid(),
        'legal_name' => 'Auth Test Company B',
        'trade_name' => 'Auth Test B',
    ]);

    $this->branchB = Branch::create([
        'company_id' => $this->companyB->id,
        'code' => 'AUTH-B',
        'name' => 'Auth Branch B',
    ]);

    $this->userB = User::create([
        'name' => 'Test User B',
        'email' => 'user-b-' . uniqid() . '@test.com',
        'password' => 'password456',
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'pos_pin_hash' => Hash::make('9999'),
        'role' => 'cashier',
        'locale' => 'es-CL',
        'is_active' => true,
    ]);
});

// ============================================
// POST /api/v1/auth/login
// ============================================

test('POST /api/v1/auth/login con credenciales válidas', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $this->userA->email,
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'user' => [
                'uuid',
                'name',
                'email',
                'role',
                'company_id',
                'branch_id',
            ],
        ])
        ->assertJsonPath('user.email', $this->userA->email)
        ->assertJsonPath('user.company_id', $this->companyA->id)
        ->assertJsonPath('token_type', 'bearer');
});

test('POST /api/v1/auth/login con email incorrecto', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nonexistent@test.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'invalid_credentials');
});

test('POST /api/v1/auth/login con password incorrecto', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $this->userA->email,
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'invalid_credentials');
});

test('POST /api/v1/auth/login con usuario inactivo', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $this->inactiveUserA->email,
        'password' => 'password123',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'invalid_credentials');
});

test('POST /api/v1/auth/login valida campos requeridos', function () {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

test('POST /api/v1/auth/login valida formato de email', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'invalid-email',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

// ============================================
// POST /api/v1/auth/login/pos
// ============================================

test('POST /api/v1/auth/login/pos con PIN válido', function () {
    $response = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => $this->branchA->id,
        'pin' => '1234',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'user' => [
                'uuid',
                'name',
                'email',
                'role',
                'company_id',
                'branch_id',
            ],
        ])
        ->assertJsonPath('user.branch_id', $this->branchA->id)
        ->assertJsonPath('user.company_id', $this->companyA->id);
});

test('POST /api/v1/auth/login/pos con PIN incorrecto', function () {
    $response = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => $this->branchA->id,
        'pin' => '9999',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'invalid_credentials');
});

test('POST /api/v1/auth/login/pos con branch_id inexistente', function () {
    $response = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => 999999,
        'pin' => '1234',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['branch_id']);
});

test('POST /api/v1/auth/login/pos valida formato de PIN', function () {
    $response = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => $this->branchA->id,
        'pin' => '123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['pin']);
});

test('POST /api/v1/auth/login/pos valida campos requeridos', function () {
    $response = $this->postJson('/api/v1/auth/login/pos', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['branch_id', 'pin']);
});

// ============================================
// POST /api/v1/auth/refresh
// ============================================

test('POST /api/v1/auth/refresh con token válido', function () {
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => $this->userA->email,
        'password' => 'password123',
    ]);

    $token = $loginResponse->json('access_token');

    $response = $this->withHeaders(authHeaders($token))
        ->postJson('/api/v1/auth/refresh');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'user',
        ]);

    $newToken = $response->json('access_token');

    expect($newToken)->not->toBe($token);
});

test('POST /api/v1/auth/refresh sin autenticación retorna 401', function () {
    $response = $this->postJson('/api/v1/auth/refresh');

    $response->assertStatus(401);
});

// ============================================
// POST /api/v1/auth/logout
// ============================================

test('POST /api/v1/auth/logout invalida token', function () {
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => $this->userA->email,
        'password' => 'password123',
    ]);

    $token = $loginResponse->json('access_token');

    $response = $this->withHeaders(authHeaders($token))
        ->postJson('/api/v1/auth/logout');

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Sesión cerrada correctamente.');

    // Limpiar estado entre requests (guard JWT cachea usuario del request anterior)
    switchJwtUser();

    $meResponse = $this->withHeaders(authHeaders($token))
        ->getJson('/api/v1/auth/me');

    $meResponse->assertStatus(401);
});

test('POST /api/v1/auth/logout sin autenticación retorna 401', function () {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertStatus(401);
});

// ============================================
// GET /api/v1/auth/me
// ============================================

test('GET /api/v1/auth/me retorna usuario autenticado', function () {
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => $this->userA->email,
        'password' => 'password123',
    ]);

    $token = $loginResponse->json('access_token');

    $response = $this->withHeaders(authHeaders($token))
        ->getJson('/api/v1/auth/me');

    $response->assertStatus(200)
        ->assertJsonPath('data.uuid', $this->userA->uuid)
        ->assertJsonPath('data.name', $this->userA->name)
        ->assertJsonPath('data.email', $this->userA->email)
        ->assertJsonPath('data.role', $this->userA->role)
        ->assertJsonPath('data.company_id', $this->companyA->id)
        ->assertJsonPath('data.branch_id', $this->branchA->id);
});

test('GET /api/v1/auth/me sin autenticación retorna 401', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(401);
});

// ============================================
// Cross-tenant isolation
// ============================================

test('Usuario de empresa A puede loguearse correctamente', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $this->userA->email,
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('user.company_id', $this->companyA->id)
        ->assertJsonPath('user.branch_id', $this->branchA->id);
});

test('Usuario de empresa B puede loguearse correctamente', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $this->userB->email,
        'password' => 'password456',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('user.company_id', $this->companyB->id)
        ->assertJsonPath('user.branch_id', $this->branchB->id);
});

test('POS login solo retorna usuarios de la branch especificada', function () {
    $response = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => $this->branchB->id,
        'pin' => '1234',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'invalid_credentials');
});

test('POS login con PIN correcto de branch B', function () {
    $response = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => $this->branchB->id,
        'pin' => '9999',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('user.branch_id', $this->branchB->id)
        ->assertJsonPath('user.company_id', $this->companyB->id);
});
