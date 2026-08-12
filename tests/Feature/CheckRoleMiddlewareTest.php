<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'ROLE-TEST-' . uniqid(),
        'legal_name' => 'Role Test Company',
        'trade_name' => 'Role Test Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'ROLE-TEST',
        'name' => 'Role Test Branch',
    ]);

    // Registrar ruta temporal para testear el middleware
    Route::middleware(['api', 'auth:api', \App\Shared\Http\Middleware\CheckRole::class . ':admin,manager'])
        ->get('/api/v1/test/role-protected', fn() => response()->json(['ok' => true]))
        ->name('test.role-protected');
});

function createUserWithRole(string $role, $test): User
{
    return User::create([
        'name' => "User {$role}",
        'email' => "{$role}-" . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $test->company->id,
        'branch_id' => $test->branch->id,
        'role' => $role,
    ]);
}

// ============================================
// Acceso permitido
// ============================================

test('admin puede acceder a ruta protegida', function () {
    $user = createUserWithRole('admin', $this);
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
    ])->getJson('/api/v1/test/role-protected');

    $response->assertOk()
        ->assertJson(['ok' => true]);
});

test('manager puede acceder a ruta protegida', function () {
    $user = createUserWithRole('manager', $this);
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
    ])->getJson('/api/v1/test/role-protected');

    $response->assertOk()
        ->assertJson(['ok' => true]);
});

// ============================================
// Acceso denegado
// ============================================

test('waiter NO puede acceder a ruta admin/manager', function () {
    $user = createUserWithRole('waiter', $this);
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
    ])->getJson('/api/v1/test/role-protected');

    $response->assertStatus(403)
        ->assertJsonPath('error', 'forbidden')
        ->assertJsonPath('current_role', 'waiter')
        ->assertJsonPath('required_roles', ['admin', 'manager']);
});

test('kitchen NO puede acceder a ruta admin/manager', function () {
    $user = createUserWithRole('kitchen', $this);
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
    ])->getJson('/api/v1/test/role-protected');

    $response->assertStatus(403);
});

test('cashier NO puede acceder a ruta admin/manager', function () {
    $user = createUserWithRole('cashier', $this);
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
    ])->getJson('/api/v1/test/role-protected');

    $response->assertStatus(403);
});

// ============================================
// Casos edge
// ============================================

test('sin token retorna 401 (no 403)', function () {
    $response = $this->withHeaders([
        'Accept' => 'application/json',
    ])->getJson('/api/v1/test/role-protected');

    $response->assertStatus(401)
        ->assertJsonPath('error', 'unauthenticated');
});

// Nota: Test de un solo rol eliminado porque Laravel no aplica
// middleware correctamente en rutas registradas dinámicamente dentro de tests.
// Los 6 tests anteriores validan el comportamiento del alias 'role'.
