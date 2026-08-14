<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use App\Shared\Application\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => '76.999.999-9',
        'legal_name' => 'Tenant Restriction Test SpA',
        'trade_name' => 'Tenant Restriction Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'TRT',
        'name' => 'Tenant Restriction Branch',
    ]);

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'testuser@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
        'is_active' => true,
    ]);

    $this->token = JWTAuth::fromUser($this->user);
});

// ============================================
// Rutas protegidas sin autenticación
// ============================================

test('ruta protegida sin auth retorna 401', function () {
    $response = $this->getJson('/api/v1/orders');
    
    // auth:api middleware lanza 401 antes que TenantContextMiddleware
    // Solo verificamos el status, no el mensaje específico
    $response->assertStatus(401);
    expect($response->json())->toHaveKey('message');
});

test('ruta protegida con headers falsos retorna 401', function () {
    // Intentar acceder con headers de otra empresa sin auth
    $response = $this->withHeaders([
        'X-Company-ID' => '00000000-0000-0000-0000-000000000000',
        'X-Branch-ID' => '00000000-0000-0000-0000-000000000000',
    ])->getJson('/api/v1/orders');
    
    $response->assertStatus(401);
});

test('ruta protegida con auth válida retorna 200', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
    ])->getJson('/api/v1/orders');
    
    $response->assertStatus(200);
});

// ============================================
// Rutas públicas sin autenticación
// ============================================

test('ruta pública de login permite acceso sin auth', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'testuser@test.com',
        'password' => 'password123',
    ]);
    
    // Puede retornar 200 (login exitoso) o 422 (validación)
    // pero NUNCA 401
    expect($response->status())->not->toBe(401);
});

test('ruta pública de health check permite acceso sin auth', function () {
    $response = $this->getJson('/api/health');
    
    // Puede ser 200 o 404 si no existe la ruta, pero no 401
    expect($response->status())->not->toBe(401);
});

test('ruta pública de menú permite acceso sin auth', function () {
    $response = $this->getJson("/api/v1/branches/{$this->branch->uuid}/menu");
    
    // Puede ser 200, 404 o 422, pero no 401
    expect($response->status())->not->toBe(401);
});

// ============================================
// Prevención de inyección de headers
// ============================================

test('headers X-Company-ID falsos en ruta protegida son ignorados', function () {
    // Intentar inyectar contexto de otra empresa
    $fakeCompanyId = '11111111-1111-1111-1111-111111111111';
    $fakeBranchId = '22222222-2222-2222-2222-222222222222';
    
    $response = $this->withHeaders([
        'X-Company-ID' => $fakeCompanyId,
        'X-Branch-ID' => $fakeBranchId,
    ])->getJson('/api/v1/orders');
    
    // Debe rechazar porque la ruta requiere auth
    $response->assertStatus(401);
});

test('middleware TenantContext establece contexto guest en rutas públicas', function () {
    // Test unitario del middleware directamente (más confiable que test de integración)
    $middleware = new \App\Shared\Http\Middleware\TenantContextMiddleware(
        app(TenantContext::class)
    );
    
    $request = \Illuminate\Http\Request::create(
        '/api/v1/auth/login',
        'POST',
        [],
        [],
        [],
        [
            'HTTP_X-Company-ID' => $this->company->uuid,
            'HTTP_X-Branch-ID' => $this->branch->uuid,
        ]
    );
    
    // Simular que no hay usuario autenticado
    $request->setUserResolver(fn() => null);
    
    $tenantContext = app(TenantContext::class);
    $tenantContext->clear();
    
    $middleware->handle($request, function ($req) {
        return response()->json(['ok' => true]);
    });
    
    // El contexto debe estar establecido con rol 'guest'
    expect($tenantContext->hasCompany())->toBeTrue();
    expect($tenantContext->companyId())->toBe($this->company->id);
    expect($tenantContext->role())->toBe('guest');
    expect($tenantContext->userId())->toBeNull();
});

test('branch debe pertenecer a company en headers de ruta pública', function () {
    // Crear otra empresa
    $otherCompany = Company::create([
        'tax_id' => '76.888.888-8',
        'legal_name' => 'Other Company SpA',
        'trade_name' => 'Other Company',
    ]);

    $otherBranch = Branch::create([
        'company_id' => $otherCompany->id,
        'code' => 'OTH',
        'name' => 'Other Branch',
    ]);

    $middleware = new \App\Shared\Http\Middleware\TenantContextMiddleware(
        app(TenantContext::class)
    );
    
    // Intentar usar branch de otra empresa
    $request = \Illuminate\Http\Request::create(
        '/api/v1/auth/login',
        'POST',
        [],
        [],
        [],
        [
            'HTTP_X-Company-ID' => $this->company->uuid, // Empresa A
            'HTTP_X-Branch-ID' => $otherBranch->uuid,    // Branch de Empresa B
        ]
    );
    
    $request->setUserResolver(fn() => null);
    
    $tenantContext = app(TenantContext::class);
    $tenantContext->clear();
    
    $middleware->handle($request, function ($req) {
        return response()->json(['ok' => true]);
    });
    
    // El contexto debe tener company A pero branch NULL (no coincide)
    expect($tenantContext->hasCompany())->toBeTrue();
    expect($tenantContext->companyId())->toBe($this->company->id);
    expect($tenantContext->branchId())->toBeNull(); // Branch rechazado
    expect($tenantContext->role())->toBe('guest');
});

// ============================================
// Configuración de rutas públicas
// ============================================

test('configuración de rutas públicas está cargada', function () {
    $config = config('tenant_public_routes');
    
    expect($config)->toBeArray();
    expect($config)->toHaveKey('routes');
    expect($config)->toHaveKey('force_auth_routes');
    expect($config)->toHaveKey('allowed_public_headers');
    
    expect($config['routes'])->toBeArray();
    expect(count($config['routes']))->toBeGreaterThan(0);
});

test('force_auth_routes previene acceso sin auth aunque esté en rutas públicas', function () {
    // Verificar que force_auth_routes tiene prioridad
    $middleware = new \App\Shared\Http\Middleware\TenantContextMiddleware(
        app(TenantContext::class)
    );
    
    $request = \Illuminate\Http\Request::create('/api/v1/orders', 'GET');
    $request->setUserResolver(fn() => null);
    
    $tenantContext = app(TenantContext::class);
    $tenantContext->clear();
    
    $exceptionThrown = false;
    try {
        $middleware->handle($request, function ($req) {
            return response()->json(['ok' => true]);
        });
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        $exceptionThrown = true;
        expect($e->getStatusCode())->toBe(401);
    }
    
    expect($exceptionThrown)->toBeTrue();
});
