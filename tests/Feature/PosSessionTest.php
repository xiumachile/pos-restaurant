<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush(); // Limpiar cache antes de cada test

    $this->company = Company::create([
        'tax_id' => 'POSSESSION-' . uniqid(),
        'legal_name' => 'POS Session Test',
        'trade_name' => 'POS Session',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'POS',
        'name' => 'POS Session Branch',
    ]);

    // Crear cajero con PIN
    $this->cashier = User::create([
        'name' => 'Test Cashier',
        'email' => 'cashier-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
        'pos_pin_hash' => password_hash('1234', PASSWORD_BCRYPT),
    ]);
});

test('POST /api/v1/auth/pos-session genera token con PIN válido', function () {
    $response = $this->postJson('/api/v1/auth/pos-session', [
        'branch_id' => $this->branch->id,
        'pin' => '1234',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'session_token',
            'token_type',
            'expires_in',
            'branch_id',
            'user' => ['uuid', 'name', 'role'],
        ])
        ->assertJsonPath('token_type', 'pos_session')
        ->assertJsonPath('expires_in', 300)
        ->assertJsonPath('user.name', 'Test Cashier');

    // Verificar que el token está en cache
    $token = $response->json('session_token');
    $cacheKey = "pos_session:{$this->branch->id}:{$token}";
    expect(Cache::get($cacheKey))->toBe($this->cashier->id);
});

test('POST /api/v1/auth/pos-session rechaza PIN inválido', function () {
    $response = $this->postJson('/api/v1/auth/pos-session', [
        'branch_id' => $this->branch->id,
        'pin' => '9999',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'invalid_credentials');
});

test('login con session_token es O(1) y one-time use', function () {
    // Paso 1: Generar token
    $sessionResponse = $this->postJson('/api/v1/auth/pos-session', [
        'branch_id' => $this->branch->id,
        'pin' => '1234',
    ]);

    $sessionToken = $sessionResponse->json('session_token');

    // Paso 2: Login con token (O(1))
    $loginResponse = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => $this->branch->id,
        'session_token' => $sessionToken,
    ]);

    $loginResponse->assertStatus(200)
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'user',
        ])
        ->assertJsonPath('user.name', 'Test Cashier');

    // Paso 3: Verificar que el token fue eliminado (one-time use)
    $cacheKey = "pos_session:{$this->branch->id}:{$sessionToken}";
    expect(Cache::get($cacheKey))->toBeNull();

    // Paso 4: Intentar reusar el token debe fallar
    $reuseResponse = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => $this->branch->id,
        'session_token' => $sessionToken,
    ]);

    $reuseResponse->assertStatus(401);
});

test('session_token expira después del TTL', function () {
    // Generar token
    $sessionResponse = $this->postJson('/api/v1/auth/pos-session', [
        'branch_id' => $this->branch->id,
        'pin' => '1234',
    ]);

    $sessionToken = $sessionResponse->json('session_token');

    // Simular expiración: viajar en el tiempo 6 minutos
    $this->travel(6)->minutes();

    // Intentar login con token expirado
    $response = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => $this->branch->id,
        'session_token' => $sessionToken,
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'invalid_credentials');
});

test('session_token de otra sucursal no funciona', function () {
    // Crear segunda sucursal
    $branch2 = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'POS2',
        'name' => 'POS Session Branch 2',
    ]);

    // Generar token para branch 1
    $sessionResponse = $this->postJson('/api/v1/auth/pos-session', [
        'branch_id' => $this->branch->id,
        'pin' => '1234',
    ]);

    $sessionToken = $sessionResponse->json('session_token');

    // Intentar usar token en branch 2 (debe fallar)
    $response = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => $branch2->id,
        'session_token' => $sessionToken,
    ]);

    $response->assertStatus(401);
});

test('flujo legacy con PIN sigue funcionando (backwards compatible)', function () {
    $response = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => $this->branch->id,
        'pin' => '1234',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['access_token', 'user'])
        ->assertJsonPath('user.name', 'Test Cashier');
});

test('validación: requiere pin o session_token (uno de los dos)', function () {
    // Sin pin ni session_token
    $response = $this->postJson('/api/v1/auth/login/pos', [
        'branch_id' => $this->branch->id,
    ]);

    $response->assertStatus(422);
});
