<?php

namespace Tests\Feature;

use App\Shared\Domain\Entities\IdempotencyKey;
use App\Models\User;
use App\Shared\Http\Middleware\IdempotencyKeyMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P1-011: Validación de recuperación de Zombie Locks en Idempotency Middleware.
 */
class IdempotencyZombieLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Registrar una ruta de prueba con el middleware de idempotencia
        Route::post('/api/test-idempotency', function () {
            return response()->json(['success' => true, 'message' => 'Processed successfully']);
        })->middleware(IdempotencyKeyMiddleware::class);
    }

    private function generateRequestHash(string $method, string $path, array $body): string
    {
        return hash('sha256', json_encode([
            'method' => $method,
            'path' => $path,
            'body' => $body,
        ]));
    }

    public function test_debe_recuperar_zombie_lock_cuando_processing_until_ha_expirado()
    {
        $user = User::factory()->create(['uuid' => Str::uuid()->toString()]);
        $idempotencyKey = Str::uuid()->toString();
        $payload = ['amount' => 1000];
        $requestHash = $this->generateRequestHash('POST', 'api/test-idempotency', $payload);

        // 1. Simular un registro zombie (processing_until expirado)
        $zombieRecord = IdempotencyKey::create([
            'key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'endpoint' => 'api/test-idempotency',
            'user_id' => $user->id,
            'expires_at' => now()->addHours(22),
            'processing_until' => now()->subHour(), // ¡LEASE EXPIRADO!
            'response_code' => null,
            'response_body' => null,
        ]);

        // 2. Simular un nuevo request con la misma key
        $response = $this->actingAs($user)
            ->withHeaders([
                'Idempotency-Key' => $idempotencyKey,
                'Content-Type' => 'application/json',
            ])
            ->postJson('/api/test-idempotency', $payload);

        // 3. Verificar que NO devolvió 409 (request_in_progress), sino que procesó la solicitud
        $this->assertNotEquals(409, $response->status());
        $this->assertEquals(200, $response->status());
        
        // 4. Verificar que el registro actualizó su processing_until (tomó ownership)
        $zombieRecord->refresh();
        $this->assertNotNull($zombieRecord->processing_until);
        $this->assertTrue($zombieRecord->processing_until->isFuture());
    }

    public function test_debe_devolver_409_si_el_lease_aun_no_ha_expirado()
    {
        $user = User::factory()->create(['uuid' => Str::uuid()->toString()]);
        $idempotencyKey = Str::uuid()->toString();
        $payload = ['amount' => 2000];
        $requestHash = $this->generateRequestHash('POST', 'api/test-idempotency', $payload);

        // 1. Simular un registro activo con processing_until en el futuro
        IdempotencyKey::create([
            'key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'endpoint' => 'api/test-idempotency',
            'user_id' => $user->id,
            'expires_at' => now()->addHours(23),
            'processing_until' => now()->addMinutes(5), // Lease activo
            'response_code' => null,
            'response_body' => null,
        ]);

        // 2. Simular un nuevo request con la misma key
        $response = $this->actingAs($user)
            ->withHeaders([
                'Idempotency-Key' => $idempotencyKey,
                'Content-Type' => 'application/json',
            ])
            ->postJson('/api/test-idempotency', $payload);

        // 3. Verificar que devolvió 409 request_in_progress
        $response->assertStatus(409);
        $response->assertJson(['error' => 'request_in_progress']);
    }
}
