<?php

use App\Shared\Domain\Entities\IdempotencyKey;
use App\Shared\Http\Middleware\IdempotencyKeyMiddleware;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Traits\InjectsIdempotencyKey;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::forceCreate([
        'tax_id' => '76.777.666-5',
        'legal_name' => 'Idempotency Test SpA',
        'trade_name' => 'Idempotency Test',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'IDEMP',
        'name' => 'Idempotency Branch',
    ]);

    $this->cashier = User::forceCreate([
        'name' => 'Cashier User',
        'email' => 'cashier-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    // Ruta de test CON middleware de idempotencia (usando clase completa)
    Route::middleware(['api', 'auth:api', IdempotencyKeyMiddleware::class])
        ->prefix('api/v1')
        ->group(function () {
            Route::post('/test-idempotent', function () {
                return response()->json([
                    'success' => true,
                    'timestamp' => now()->timestamp,
                    'random' => rand(1000, 9999),
                ]);
            })->name('test.idempotent');

            Route::post('/test-idempotent-error', function () {
                return response()->json(['error' => 'test'], 500);
            })->name('test.idempotent.error');
        });

    // Ruta de test SIN middleware de idempotencia
    Route::middleware(['api', 'auth:api'])
        ->prefix('api/v1')
        ->group(function () {
            Route::post('/test-normal', function () {
                return response()->json(['success' => true]);
            })->name('test.normal');
        });
});

// ============================================
// Tests de entidad IdempotencyKey
// ============================================

test('IdempotencyKey se puede crear con datos válidos', function () {
    $key = IdempotencyKey::create([
        'key' => Str::uuid(),
        'request_hash' => hash('sha256', 'test'),
        'response_body' => ['success' => true],
        'response_code' => 200,
        'user_id' => $this->cashier->id,
        'endpoint' => '/api/v1/test',
        'expires_at' => now()->addHours(24),
    ]);

    expect($key)->not->toBeNull()
        ->and($key->hasValidResponse())->toBeTrue()
        ->and($key->isExpired())->toBeFalse();
});

test('IdempotencyKey detecta expiración correctamente', function () {
    $expired = IdempotencyKey::create([
        'key' => Str::uuid(),
        'request_hash' => hash('sha256', 'test'),
        'response_body' => ['success' => true],
        'response_code' => 200,
        'expires_at' => now()->subHour(),
    ]);

    $valid = IdempotencyKey::create([
        'key' => Str::uuid(),
        'request_hash' => hash('sha256', 'test2'),
        'response_body' => ['success' => true],
        'response_code' => 200,
        'expires_at' => now()->addHour(),
    ]);

    expect($expired->isExpired())->toBeTrue()
        ->and($expired->hasValidResponse())->toBeFalse()
        ->and($valid->isExpired())->toBeFalse()
        ->and($valid->hasValidResponse())->toBeTrue();
});

test('Scope valid filtra keys no expiradas', function () {
    IdempotencyKey::create([
        'key' => Str::uuid(),
        'request_hash' => hash('sha256', 'expired'),
        'response_body' => ['data' => 'old'],
        'response_code' => 200,
        'expires_at' => now()->subHour(),
    ]);

    IdempotencyKey::create([
        'key' => Str::uuid(),
        'request_hash' => hash('sha256', 'valid'),
        'response_body' => ['data' => 'new'],
        'response_code' => 200,
        'expires_at' => now()->addHour(),
    ]);

    expect(IdempotencyKey::valid()->count())->toBe(1);
    expect(IdempotencyKey::expired()->count())->toBe(1);
});

test('cleanupExpired elimina keys expiradas', function () {
    for ($i = 0; $i < 2; $i++) {
        IdempotencyKey::create([
            'key' => Str::uuid(),
            'request_hash' => hash('sha256', "expired-$i"),
            'response_body' => ['data' => 'old'],
            'response_code' => 200,
            'expires_at' => now()->subHour(),
        ]);
    }

    IdempotencyKey::create([
        'key' => Str::uuid(),
        'request_hash' => hash('sha256', 'valid'),
        'response_body' => ['data' => 'new'],
        'response_code' => 200,
        'expires_at' => now()->addHour(),
    ]);

    expect(IdempotencyKey::count())->toBe(3);

    $deleted = IdempotencyKey::cleanupExpired();

    expect($deleted)->toBe(2);
    expect(IdempotencyKey::count())->toBe(1);
});

// ============================================
// Tests del middleware
// ============================================

test('middleware exige header Idempotency-Key en rutas protegidas', function () {
    // Pasar cadena vacía: el trait respeta el valor (no inyecta),
    // pero el middleware lo trata como faltante (!'' === true)
    $response = $this->actingAs($this->cashier, 'api')
        ->postJson('/api/v1/test-idempotent', ['data' => 'test'], ['Idempotency-Key' => '']);

    $response->assertStatus(400)
        ->assertJsonPath('error', 'Idempotency-Key header is required');
});

test('middleware rechaza UUID inválido', function () {
    $response = $this->actingAs($this->cashier, 'api')
        ->withHeaders(['Idempotency-Key' => 'not-a-uuid'])
        ->postJson('/api/v1/test-idempotent', ['data' => 'test']);

    $response->assertStatus(400)
        ->assertJsonPath('error', 'Invalid Idempotency-Key format');
});

test('middleware acepta UUID válido y ejecuta el request', function () {
    $response = $this->actingAs($this->cashier, 'api')
        ->withHeaders(['Idempotency-Key' => Str::uuid()])
        ->postJson('/api/v1/test-idempotent', ['data' => 'test']);

    $response->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('middleware cachea respuesta exitosa', function () {
    $key = Str::uuid();

    $response = $this->actingAs($this->cashier, 'api')
        ->withHeaders(['Idempotency-Key' => $key])
        ->postJson('/api/v1/test-idempotent', ['data' => 'test']);

    $response->assertStatus(200);

    $record = IdempotencyKey::where('key', $key)->first();
    
    expect($record)->not->toBeNull()
        ->and($record->response_code)->toBe(200)
        ->and($record->response_body['success'])->toBe(true);
});

test('middleware detecta conflicto de key con diferente request', function () {
    $key = Str::uuid();

    // Crear registro previo con un hash específico
    IdempotencyKey::create([
        'key' => $key,
        'request_hash' => hash('sha256', 'completely-different-request'),
        'response_body' => ['success' => true],
        'response_code' => 200,
        'user_id' => $this->cashier->id,
        'endpoint' => 'api/v1/test-idempotent',
        'expires_at' => now()->addHour(),
    ]);

    // Intentar usar la misma key con request diferente
    $response = $this->actingAs($this->cashier, 'api')
        ->withHeaders(['Idempotency-Key' => $key])
        ->postJson('/api/v1/test-idempotent', ['data' => 'changed']);

    $response->assertStatus(409)
        ->assertJsonPath('error', 'Idempotency-Key conflict');
});

test('middleware retorna respuesta cacheada en reintento con mismos datos', function () {
    $key = Str::uuid();

    // Primera petición
    $response1 = $this->actingAs($this->cashier, 'api')
        ->withHeaders(['Idempotency-Key' => $key])
        ->postJson('/api/v1/test-idempotent', ['data' => 'test']);

    $response1->assertStatus(200);
    $random1 = $response1->json('random');

    // Segunda petición con mismos datos (reintento)
    $response2 = $this->actingAs($this->cashier, 'api')
        ->withHeaders(['Idempotency-Key' => $key])
        ->postJson('/api/v1/test-idempotent', ['data' => 'test']);

    $response2->assertStatus(200);
    $random2 = $response2->json('random');

    // El random debe ser idéntico (respuesta cacheada)
    expect($random1)->toBe($random2)
        ->and(IdempotencyKey::where('key', $key)->count())->toBe(1);
});

test('middleware no cachea respuestas de error', function () {
    $key = Str::uuid();

    $response = $this->actingAs($this->cashier, 'api')
        ->withHeaders(['Idempotency-Key' => $key])
        ->postJson('/api/v1/test-idempotent-error', ['data' => 'test']);

    $response->assertStatus(500);

    expect(IdempotencyKey::where('key', $key)->count())->toBe(0);
});

test('middleware ignora rutas sin middleware', function () {
    $response = $this->actingAs($this->cashier, 'api')
        ->postJson('/api/v1/test-normal', ['data' => 'test']);

    $response->assertStatus(200);
});

test('middleware ignora requests GET (solo aplica a mutaciones)', function () {
    // Crear ruta GET con middleware
    Route::middleware(['api', 'auth:api', IdempotencyKeyMiddleware::class])
        ->prefix('api/v1')
        ->group(function () {
            Route::get('/test-get', function () {
                return response()->json(['success' => true]);
            })->name('test.get');
        });

    $response = $this->actingAs($this->cashier, 'api')
        ->getJson('/api/v1/test-get');

    // No debería requerir header
    $response->assertStatus(200);
});
