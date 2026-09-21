<?php

namespace App\Shared\Http\Middleware;

use App\Shared\Application\TenantContext;
use App\Shared\Domain\Entities\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Middleware de idempotencia con patrón INSERT-first (Stripe/Shopify).
 *
 * PROBLEMA RESUELTO (P1):
 * El patrón anterior SELECT → business → INSERT tenía una ventana de carrera
 * donde dos requests simultáneos podían ejecutar el negocio dos veces antes
 * de descubrir la colisión por UNIQUE constraint.
 *
 * PATRÓN INSERT-FIRST:
 * 1. INSERT idempotency_key (claim atomic con status 'pending')
 * 2. Si UNIQUE violation → retornar response cacheada (replay)
 * 3. Si éxito → ejecutar negocio
 * 4. UPDATE con response final
 * 5. Si negocio falla → DELETE key (permite retry)
 *
 * GARANTÍAS:
 * - Solo UN request ejecuta el negocio por idempotency-key
 * - Requests duplicados reciben response cacheada (200 con header Idempotency-Replayed)
 * - Requests con key en progreso reciben 409 Conflict
 * - Requests con payload diferente reciben 409 Conflict
 *
 * ADR-015: Scoped a tenant cuando hay contexto, global cuando no hay.
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
                'message' => 'Este endpoint requiere el header Idempotency-Key (UUIDv4) para prevenir procesamiento duplicado.',
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

        $requestHash = $this->generateRequestHash($request);
        $cacheKey = $hasTenantContext
            ? "idempotency:{$companyId}:{$idempotencyKey}"
            : "idempotency:global:{$idempotencyKey}";

        // PASO 0: Fast path - si hay response cacheada en Redis, retornar inmediatamente
        $cachedResponse = Cache::get($cacheKey);
        if ($cachedResponse && $this->isCacheValid($cachedResponse)) {
            if ($cachedResponse['request_hash'] === $requestHash) {
                Log::info('IdempotencyKey: Replay from Redis cache', [
                    'key' => $idempotencyKey,
                    'company_id' => $companyId,
                ]);
                return $this->replayResponse($cachedResponse);
            }
            return $this->conflictResponse('Idempotency-Key usado con payload diferente');
        }

        // PASO 1: INSERT FIRST (claim atomic de la key)
        // Este es el paso CRÍTICO que previene la race condition.
        // El UNIQUE constraint garantiza que solo UN request gana el derecho a procesar.
        $dbData = [
            'key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'endpoint' => $request->path(),
            'user_id' => auth()->id(),
            'expires_at' => now()->addHours(self::DEFAULT_TTL_HOURS),
            // response_code = NULL indica "in progress"
            'response_code' => null,
            'response_body' => null,
        ];

        if ($hasTenantContext) {
            $dbData['company_id'] = $companyId;
            $dbData['branch_id'] = $branchId;
        }

        try {
            // Intentar INSERT (ganar el derecho a procesar)
            $idempotencyRecord = IdempotencyKey::withoutGlobalScopes()->create($dbData);
            
            Log::info('IdempotencyKey: Claimed (INSERT successful)', [
                'key' => $idempotencyKey,
                'company_id' => $companyId,
                'endpoint' => $request->path(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Alguien más ganó el claim → manejar según estado
            return $this->handleDuplicateKey($idempotencyKey, $requestHash, $companyId, $hasTenantContext, $cacheKey);
        }

        // PASO 2: Ejecutar negocio (solo si ganamos el claim)
        try {
            /** @var SymfonyResponse $response */
            $response = $next($request);

            // PASO 3: Actualizar con response final (solo si es exitosa 2xx)
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $responseBody = json_decode($response->getContent(), true);
                
                $idempotencyRecord->update([
                    'response_code' => $response->getStatusCode(),
                    'response_body' => $responseBody,
                ]);

                // Write-through: cachear en Redis para próximos requests
                Cache::put($cacheKey, [
                    'request_hash' => $requestHash,
                    'response_code' => $response->getStatusCode(),
                    'response_body' => $responseBody,
                ], now()->addHours(self::DEFAULT_TTL_HOURS));

                Log::info('IdempotencyKey: Business completed and cached', [
                    'key' => $idempotencyKey,
                    'status_code' => $response->getStatusCode(),
                ]);
            } else {
                // Respuesta no exitosa → eliminar key para permitir retry
                $idempotencyRecord->delete();
                Cache::forget($cacheKey);
            }

            return $response;
        } catch (\Throwable $e) {
            // PASO 4: Si el negocio falla, eliminar key para permitir retry
            Log::warning('IdempotencyKey: Business failed, releasing key', [
                'key' => $idempotencyKey,
                'error' => $e->getMessage(),
            ]);

            try {
                $idempotencyRecord->delete();
                Cache::forget($cacheKey);
            } catch (\Throwable $cleanupError) {
                Log::error('IdempotencyKey: Failed to cleanup key', [
                    'key' => $idempotencyKey,
                    'error' => $cleanupError->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Maneja el caso de key duplicada (otro request ganó el claim).
     */
    private function handleDuplicateKey(
        string $key,
        string $requestHash,
        ?int $companyId,
        bool $hasTenantContext,
        string $cacheKey
    ): SymfonyResponse {
        // Buscar el registro existente
        $query = IdempotencyKey::withoutGlobalScopes();
        
        if ($hasTenantContext) {
            $query->where('company_id', $companyId);
        } else {
            $query->whereNull('company_id');
        }

        $existing = $query->where('key', $key)->first();

        if (!$existing) {
            // Raro: UNIQUE violation pero no existe (probablemente expiró entre medias)
            return $this->conflictResponse('Idempotency-Key conflict');
        }

        // Verificar expiración
        if ($existing->isExpired()) {
            // Eliminar y permitir retry (el cliente debería generar nueva key)
            return $this->conflictResponse('Idempotency-Key expirada, genere una nueva');
        }

        // Verificar que el payload sea el mismo
        if ($existing->request_hash !== $requestHash) {
            return $this->conflictResponse('Idempotency-Key ya fue usado con payload diferente');
        }

        // Verificar si está completo o en progreso
        if ($existing->hasValidResponse()) {
            // Completado → replay de la response
            Log::info('IdempotencyKey: Replay from SQL', [
                'key' => $key,
                'status_code' => $existing->response_code,
            ]);

            $cacheData = [
                'request_hash' => $existing->request_hash,
                'response_code' => $existing->response_code,
                'response_body' => $existing->response_body,
            ];
            Cache::put($cacheKey, $cacheData, now()->addHours(self::DEFAULT_TTL_HOURS));

            return $this->replayResponse($cacheData);
        }

        // En progreso → 409 Conflict con Retry-After
        Log::info('IdempotencyKey: Request in progress', [
            'key' => $key,
        ]);

        return response()->json([
            'error' => 'request_in_progress',
            'message' => 'Un request con esta Idempotency-Key está siendo procesado. Reintente en unos segundos.',
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
