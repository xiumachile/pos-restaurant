<?php

namespace App\Shared\Http\Middleware;

use App\Shared\Application\TenantContext;
use App\Shared\Domain\Entities\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Middleware de idempotencia con patrón HÍBRIDO:
 * SELECT pre-check + INSERT atomic (INSERT-first).
 *
 * ARQUITECTURA (3 fases):
 *
 * FASE 0 - FAST PATH (Redis):
 *   Si hay response cacheada en Redis → replay inmediato (O(1)).
 *
 * FASE 1 - PRE-CHECK (SQL SELECT):
 *   Buscar registro existente en DB. Esto detecta:
 *   - Keys pre-existentes creadas por tests u otros procesos
 *   - Keys de requests anteriores ya completados
 *   - Conflictos de payload (mismo key, diferente request_hash)
 *   NOTA: Este SELECT NO previene race conditions (solo es pre-check).
 *
 * FASE 2 - CLAIM ATOMIC (SQL INSERT):
 *   INSERT con UNIQUE constraint. Esto PREVIENE race conditions:
 *   - Si INSERT exitoso → somos el único procesador
 *   - Si UNIQUE violation → otro request ganó el claim
 *
 * FASE 3 - EJECUCIÓN:
 *   Ejecutar negocio y guardar response para replay futuro.
 *
 * PROTECCIÓN CONTRA NULLs:
 * PostgreSQL no trata NULLs como iguales en UNIQUE constraints.
 * Para prevenir duplicados con company_id NULL, el SELECT pre-check
 * busca explícitamente con whereNull('company_id').
 *
 * ADR-015: Scoped a tenant cuando hay contexto, global cuando no hay.
 * ADR-007: Redis + SQL híbrido.
 */
class IdempotencyKeyMiddleware
{
    protected const DEFAULT_TTL_HOURS = 24;
    protected const IN_PROGRESS_TTL_SECONDS = 60;

    public function __construct(
        private TenantContext $tenantContext
    ) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Solo aplicar a mutaciones
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        // En testing, activar solo si header está presente
        if (app()->environment('testing') && !$request->hasHeader('Idempotency-Key')) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if (!$idempotencyKey) {
            return response()->json([
                'error' => 'Idempotency-Key header is required',
                'message' => 'Este endpoint requiere el header Idempotency-Key (UUIDv4).',
            ], 400);
        }

        if (!$this->isValidUuid($idempotencyKey)) {
            return response()->json([
                'error' => 'Invalid Idempotency-Key format',
                'message' => 'El Idempotency-Key debe ser un UUIDv4 válido.',
            ], 400);
        }

        // ADR-015: Obtener tenant context
        $companyId = $this->tenantContext->companyId();
        $branchId = $this->tenantContext->branchId();
        $hasTenantContext = $this->tenantContext->hasCompany();

        // Fallback: si TenantContext no tiene company pero hay usuario autenticado
        if (!$hasTenantContext && auth()->check() && auth()->user()->company_id) {
            $companyId = auth()->user()->company_id;
            $branchId = auth()->user()->branch_id;
            $hasTenantContext = true;
        }

        $requestHash = $this->generateRequestHash($request);
        $cacheKey = $hasTenantContext
            ? "idempotency:{$companyId}:{$idempotencyKey}"
            : "idempotency:global:{$idempotencyKey}";

        // ═══════════════════════════════════════════
        // FASE 0: FAST PATH (Redis)
        // ═══════════════════════════════════════════
        $cachedResponse = Cache::get($cacheKey);
        if ($cachedResponse && $this->isCacheValid($cachedResponse)) {
            if ($cachedResponse['request_hash'] === $requestHash) {
                Log::debug('IdempotencyKey: Replay from Redis', ['key' => $idempotencyKey]);
                return $this->replayResponse($cachedResponse);
            }
            return $this->conflictResponse('Idempotency-Key usado con payload diferente');
        }

        // ═══════════════════════════════════════════
        // FASE 1: PRE-CHECK (SQL SELECT)
        // Detecta keys pre-existentes (tests, requests anteriores)
        // ═══════════════════════════════════════════
        $existing = $this->findExistingKey($idempotencyKey, $companyId, $hasTenantContext);

        if ($existing) {
            return $this->handleExistingKey($existing, $requestHash, $cacheKey);
        }

        // ═══════════════════════════════════════════
        // FASE 2: CLAIM ATOMIC (SQL INSERT)
        // Previene race conditions entre requests simultáneos
        // ═══════════════════════════════════════════
        $dbData = [
            'key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'endpoint' => $request->path(),
            'user_id' => auth()->id(),
            'expires_at' => now()->addHours(self::DEFAULT_TTL_HOURS),
            'response_code' => null,
            'response_body' => null,
        ];

        if ($hasTenantContext) {
            $dbData['company_id'] = $companyId;
            $dbData['branch_id'] = $branchId;
        }

        try {
            $idempotencyRecord = IdempotencyKey::withoutGlobalScopes()->create($dbData);
        } catch (UniqueConstraintViolationException $e) {
            // Race condition: otro request ganó el claim entre nuestro SELECT e INSERT
            Log::debug('IdempotencyKey: UNIQUE violation (race condition detected)', [
                'key' => $idempotencyKey,
            ]);

            // Re-consultar el registro que ganó
            $winner = $this->findExistingKey($idempotencyKey, $companyId, $hasTenantContext);
            if ($winner) {
                return $this->handleExistingKey($winner, $requestHash, $cacheKey);
            }
            return $this->conflictResponse('Idempotency-Key conflict');
        }

        // ═══════════════════════════════════════════
        // FASE 3: EJECUCIÓN DEL NEGOCIO
        // ═══════════════════════════════════════════
        try {
            /** @var SymfonyResponse $response */
            $response = $next($request);

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $responseBody = json_decode($response->getContent(), true);

                $idempotencyRecord->update([
                    'response_code' => $response->getStatusCode(),
                    'response_body' => $responseBody,
                ]);

                Cache::put($cacheKey, [
                    'request_hash' => $requestHash,
                    'response_code' => $response->getStatusCode(),
                    'response_body' => $responseBody,
                ], now()->addHours(self::DEFAULT_TTL_HOURS));
            } else {
                // Respuesta no exitosa → liberar key para retry
                $idempotencyRecord->delete();
                Cache::forget($cacheKey);
            }

            return $response;
        } catch (\Throwable $e) {
            // Negocio falló → liberar key para retry
            Log::warning('IdempotencyKey: Business failed, releasing key', [
                'key' => $idempotencyKey,
                'error' => $e->getMessage(),
            ]);

            try {
                $idempotencyRecord->delete();
                Cache::forget($cacheKey);
            } catch (\Throwable $cleanupError) {
                Log::error('IdempotencyKey: Cleanup failed', ['key' => $idempotencyKey]);
            }

            throw $e;
        }
    }

    /**
     * Busca un registro existente por key, con scope de tenant correcto.
     * Maneja explícitamente el caso de company_id NULL (tests legacy).
     */
    private function findExistingKey(string $key, ?int $companyId, bool $hasTenantContext): ?IdempotencyKey
    {
        $query = IdempotencyKey::withoutGlobalScopes()->where('key', $key);

        if ($hasTenantContext && $companyId) {
            // Buscar en el scope del tenant O en global (company_id NULL)
            // Esto permite detectar keys pre-existentes de tests
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                  ->orWhereNull('company_id');
            });
        } else {
            // Sin tenant: buscar solo registros globales
            $query->whereNull('company_id');
        }

        return $query->first();
    }

    /**
     * Maneja una key pre-existente: replay, conflicto, o en progreso.
     */
    private function handleExistingKey(IdempotencyKey $existing, string $requestHash, string $cacheKey): SymfonyResponse
    {
        // Verificar expiración
        if ($existing->isExpired()) {
            $existing->delete();
            Cache::forget($cacheKey);
            return $this->conflictResponse('Idempotency-Key expirada, genere una nueva');
        }

        // Verificar payload
        if ($existing->request_hash !== $requestHash) {
            return $this->conflictResponse('Idempotency-Key ya fue usado con payload diferente');
        }

        // Verificar si está completado
        if ($existing->hasValidResponse()) {
            $cacheData = [
                'request_hash' => $existing->request_hash,
                'response_code' => $existing->response_code,
                'response_body' => $existing->response_body,
            ];
            Cache::put($cacheKey, $cacheData, now()->addHours(self::DEFAULT_TTL_HOURS));

            return $this->replayResponse($cacheData);
        }

        // En progreso
        return response()->json([
            'error' => 'request_in_progress',
            'message' => 'Un request con esta Idempotency-Key está siendo procesado.',
        ], 409)->header('Retry-After', (string) self::IN_PROGRESS_TTL_SECONDS);
    }

    private function replayResponse(array $cachedResponse): SymfonyResponse
    {
        return response()
            ->json($cachedResponse['response_body'], $cachedResponse['response_code'])
            ->header('Idempotency-Replayed', 'true');
    }

    private function conflictResponse(string $message): SymfonyResponse
    {
        return response()->json([
            'error' => 'Idempotency-Key conflict',
            'message' => $message,
        ], 409);
    }

    private function isCacheValid(array $cachedResponse): bool
    {
        return isset($cachedResponse['response_code'])
            && isset($cachedResponse['request_hash'])
            && $cachedResponse['response_code'] >= 200
            && $cachedResponse['response_code'] < 300;
    }

    protected function isValidUuid(string $uuid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    protected function generateRequestHash(Request $request): string
    {
        return hash('sha256', json_encode([
            'method' => $request->method(),
            'path' => $request->path(),
            'body' => $request->all(),
        ]));
    }
}
