<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'AUTH-TEST-001',
        'legal_name' => 'Auth Test Company',
        'trade_name' => 'Auth Test Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'AUTH-001',
        'name' => 'Auth Test Branch',
    ]);

    $this->user = User::create([
        'name' => 'Auth Test User',
        'email' => 'auth@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'admin',
        'locale' => 'es-CL',
        'is_active' => true,
    ]);

    // Establecer PIN POS
    $this->user->setPosPin('1234');
    $this->user->save();
});

test('POST /api/v1/auth/login retorna token con credenciales validas', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'auth@test.com',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'user' => ['uuid', 'name', 'email', 'role', 'locale', 'company_id', 'branch_id'],
        ]);
});

test('POST /api/v1/auth/login deniega credenciales invalidas', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'auth@test.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'invalid_credentials');
});

test('POST /api/v1/auth/login/pos retorna token con PIN valido', function () {
    $response = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => $this->branch->id,
        'pin' => '1234',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'token_type', 'user']);
});

test('POST /api/v1/auth/login/pos deniega PIN invalido', function () {
    $response = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => $this->branch->id,
        'pin' => '9999',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'invalid_credentials');
});

test('POST /api/v1/auth/login/pos deniega PIN con formato invalido', function () {
    $response = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => $this->branch->id,
        'pin' => 'abc',
    ]);

    $response->assertStatus(422);
});

test('GET /api/v1/auth/me retorna usuario autenticado', function () {
    $token = auth('api')->login($this->user);

    $response = $this->withHeaders([
        'Authorization' => "Bearer $token",
    ])->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('data.email', 'auth@test.com')
        ->assertJsonPath('data.role', 'admin');
});

test('GET /api/v1/auth/me sin token retorna 401', function () {
    $response = $this->getJson('/api/v1/auth/me');
    $response->assertStatus(401);
});

test('POST /api/v1/auth/logout invalida el token', function () {
    $token = auth('api')->login($this->user);

    $response = $this->withHeaders([
        'Authorization' => "Bearer $token",
    ])->getJson('/api/v1/auth/me');

    $response->assertOk();

    $response = $this->withHeaders([
        'Authorization' => "Bearer $token",
    ])->postJson('/api/v1/auth/logout');

    $response->assertOk()
        ->assertJsonPath('message', 'Sesión cerrada correctamente.');

    expect(config('jwt.blacklist_enabled'))->toBeTrue();
});

test('POST /api/v1/auth/refresh renueva el token', function () {
    $token = auth('api')->login($this->user);

    $response = $this->withHeaders([
        'Authorization' => "Bearer $token",
    ])->postJson('/api/v1/auth/refresh');

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);

    $newToken = $response->json('access_token');
    expect($newToken)->not->toBe($token);
});

test('usuario inactivo no puede autenticarse', function () {
    $this->user->is_active = false;
    $this->user->save();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'auth@test.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'invalid_credentials');
});
