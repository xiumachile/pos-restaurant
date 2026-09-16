<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de Observabilidad
 * 
 * Responsabilidades:
 * 1. Generar Request ID único para correlación de logs
 * 2. Agregar contexto automático (company, branch, user, terminal)
 * 3. Propagar contexto a todos los logs del request via Log::shareContext()
 * 4. Agregar Request ID al response header X-Request-ID
 */
class ObservabilityMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Generar o reutilizar Request ID
        $requestId = $request->header('X-Request-ID') ?? (string) Str::uuid();

        // 2. Extraer contexto del request
        $context = [
            'request_id' => $requestId,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 200),
        ];

        // 3. Agregar contexto de autenticación si existe
        $user = $request->user();
        if ($user) {
            $context['user_id'] = $user->id;
            $context['user_role'] = $user->role;
            $context['company_id'] = $user->company_id;
            $context['branch_id'] = $user->branch_id;
        }

        // 4. Agregar contexto de terminal si existe
        $terminalId = $request->header('X-Terminal-ID');
        if ($terminalId) {
            $context['terminal_id'] = $terminalId;
        }

        // 5. Agregar contexto de orden/bill/payment si están en la URL
        if (preg_match('/orders\/([a-f0-9-]+)/', $request->path(), $matches)) {
            $context['order_uuid'] = $matches[1];
        }
        if (preg_match('/bills\/([a-f0-9-]+)/', $request->path(), $matches)) {
            $context['bill_uuid'] = $matches[1];
        }
        if (preg_match('/payments\/([a-f0-9-]+)/', $request->path(), $matches)) {
            $context['payment_uuid'] = $matches[1];
        }

        // 6. Compartir contexto con todos los logs del request
        Log::shareContext($context);

        // 7. Log de entrada del request (nivel debug para no saturar)
        Log::debug('Request received', [
            'query' => $request->query(),
        ]);

        // 8. Procesar request
        $startTime = microtime(true);
        $response = $next($request);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // 9. Agregar Request ID al response header
        $response->headers->set('X-Request-ID', $requestId);

        // 10. Log de salida del request (solo si no es health check)
        if (!str_contains($request->path(), 'health')) {
            Log::info('Request completed', [
                'status' => $response->getStatusCode(),
                'duration_ms' => $duration,
            ]);
        }

        // 11. Limpiar contexto al final del request
        Log::flushSharedContext();

        return $response;
    }
}
