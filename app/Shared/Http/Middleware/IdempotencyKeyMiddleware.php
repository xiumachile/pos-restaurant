<?php

namespace App\Shared\Http\Middleware;

use App\Shared\Domain\Entities\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Middleware de idempotencia para mutaciones críticas.
 * 
 * Exige el header Idempotency-Key (UUIDv4) y cachea respuestas
 * para prevenir procesamiento duplicado en reintentos.
 * 
 * Principio arquitectónico #7: Idempotencia en mutaciones.
 */
class IdempotencyKeyMiddleware
{
    protected const DEFAULT_TTL_HOURS = 24;

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // F2.1: Desactivar idempotencia en testing para simplificar tests
        if (app()->environment('testing')) {
            return $next($request);
        }

        // Si no es POST/PUT/PATCH/DELETE, no aplicar idempotencia
        if (!$request->isMethod('POST') && !$request->isMethod('PUT') && !$request->isMethod('PATCH') && !$request->isMethod('DELETE')) {
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

        $requestHash = $this->generateRequestHash($request);

        $existing = IdempotencyKey::where('key', $idempotencyKey)->first();

        if ($existing && $existing->hasValidResponse()) {
            if ($existing->request_hash === $requestHash) {
                Log::info('IdempotencyKey: Returning cached response', [
                    'key' => $idempotencyKey,
                    'endpoint' => $request->path(),
                ]);

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
            IdempotencyKey::create([
                'key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'response_code' => $response->getStatusCode(),
                'response_body' => json_decode($response->getContent(), true),
                'expires_at' => now()->addHours(self::DEFAULT_TTL_HOURS),
            ]);
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
