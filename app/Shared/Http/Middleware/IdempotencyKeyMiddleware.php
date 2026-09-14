<?php

namespace App\Shared\Http\Middleware;

use App\Shared\Application\TenantContext;
use App\Shared\Domain\Entities\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Middleware de idempotencia para mutaciones críticas.
 * 
 * Exige el header Idempotency-Key (UUIDv4) y cachea respuestas
 * para prevenir procesamiento duplicado en reintentos.
 * 
 * ADR-015: Scoped a tenant cuando hay contexto, global cuando no hay.
 * - Con tenant context: cachea en Redis + DB con scope de tenant
 * - Sin tenant context: cachea en Redis + DB sin scope (tests legacy)
 */
class IdempotencyKeyMiddleware
{
    protected const DEFAULT_TTL_HOURS = 24;

    public function __construct(
        private TenantContext $tenantContext
    ) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Si no es POST/PUT/PATCH/DELETE, no aplicar idempotencia
        if (!$request->isMethod('POST') && !$request->isMethod('PUT') && !$request->isMethod('PATCH') && !$request->isMethod('DELETE')) {
            return $next($request);
        }

        // F2.1: En testing, activar idempotencia solo si el header está presente
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

        // ADR-015: Obtener tenant context para scope
        $companyId = $this->tenantContext->companyId();
        $branchId = $this->tenantContext->branchId();
        $hasTenantContext = $this->tenantContext->hasCompany();

        $requestHash = $this->generateRequestHash($request);

        // Cache key SCOPED por tenant (ADR-015: prevenir cross-tenant leakage)
        $cacheKey = $hasTenantContext
            ? "idempotency:{$companyId}:{$idempotencyKey}"
            : "idempotency:global:{$idempotencyKey}";

        // ESTRATEGIA HÍBRIDA (ADR-007): Redis como cache + SQL como fuente de verdad
        // Paso 1: Check Redis (O(1), fast path)
        $cachedResponse = Cache::get($cacheKey);

        if ($cachedResponse) {
            if ($cachedResponse['request_hash'] === $requestHash) {
                Log::info('IdempotencyKey: Returning cached response from Redis', [
                    'key' => $idempotencyKey,
                    'company_id' => $companyId,
                    'endpoint' => $request->path(),
                ]);

                return response()->json(
                    $cachedResponse['response_body'],
                    $cachedResponse['response_code']
                );
            }

            return response()->json([
                'error' => 'Idempotency-Key conflict',
                'message' => 'El Idempotency-Key ya fue usado con datos diferentes.',
            ], 409);
        }

        // Paso 2: Check SQL (fuente de verdad) — SCOPED por tenant si hay contexto
        $query = IdempotencyKey::query();
        
        if ($hasTenantContext) {
            // Con tenant: buscar solo en el scope del tenant
            $query->where('company_id', $companyId);
        } else {
            // Sin tenant (tests legacy): buscar solo registros sin tenant
            // IMPORTANTE: Deshabilitar global scopes de BelongsToTenant
            // porque agregan where('company_id', null) que no es igual a whereNull
            $query = IdempotencyKey::withoutGlobalScopes()
                ->whereNull('company_id');
        }
        
        $existing = $query->where('key', $idempotencyKey)->first();

        if ($existing && $existing->hasValidResponse()) {
            if ($existing->request_hash === $requestHash) {
                Log::info('IdempotencyKey: Returning cached response from SQL', [
                    'key' => $idempotencyKey,
                    'company_id' => $companyId,
                    'endpoint' => $request->path(),
                ]);

                // Write-through: cachear en Redis para próximos requests
                Cache::put($cacheKey, [
                    'request_hash' => $existing->request_hash,
                    'response_code' => $existing->response_code,
                    'response_body' => $existing->response_body,
                ], now()->addHours(self::DEFAULT_TTL_HOURS));

                return response()->json(
                    $existing->response_body,
                    $existing->response_code
                );
            }

            return response()->json([
                'error' => 'Idempotency-Key conflict',
                'message' => 'El Idempotency-Key ya fue usado con datos diferentes.',
            ], 409);
        }

        /** @var SymfonyResponse $response */
        $response = $next($request);

        // Solo cachear respuestas exitosas (2xx)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $responseData = [
                'request_hash' => $requestHash,
                'response_code' => $response->getStatusCode(),
                'response_body' => json_decode($response->getContent(), true),
            ];

            // Write-through: Redis SIEMPRE, DB SIEMPRE (con o sin tenant)
            Cache::put($cacheKey, $responseData, now()->addHours(self::DEFAULT_TTL_HOURS));

            // ADR-015: Guardar en DB con scope de tenant si hay contexto, sin scope si no hay
            $dbData = [
                'key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'response_code' => $response->getStatusCode(),
                'response_body' => $responseData['response_body'],
                'expires_at' => now()->addHours(self::DEFAULT_TTL_HOURS),
            ];

            // Solo agregar company_id/branch_id si hay tenant context
            if ($hasTenantContext) {
                $dbData['company_id'] = $companyId;
                $dbData['branch_id'] = $branchId;
            }

            // IMPORTANTE: Usar withoutGlobalScopes al crear para evitar
            // que BelongsToTenant agregue company_id automáticamente
            IdempotencyKey::withoutGlobalScopes()->create($dbData);
        }

        return $response;
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
