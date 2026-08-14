<?php

namespace App\Shared\Http\Middleware;

use App\Shared\Application\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TenantContextMiddleware
{
    public function __construct(
        private TenantContext $tenantContext
    ) {}

    /**
     * Establece el contexto de tenant desde el usuario autenticado (auth:api)
     * o desde los headers X-Company-ID y X-Branch-ID SOLO en rutas públicas.
     *
     * SEGURIDAD: Rutas protegidas REQUIEREN autenticación.
     * Rutas públicas pueden usar headers para establecer contexto limitado.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            // Usuario autenticado: usar datos del usuario (confiable)
            $this->tenantContext->setCompany(
                companyId: $user->company_id,
                branchId: $user->branch_id,
                userId: $user->id,
                locale: $user->locale ?? 'es-CL',
                role: $user->role ?? 'user',
                terminalId: null
            );
            
            return $next($request);
        }

        // SIN AUTENTICACIÓN
        $path = $request->path();
        
        // 1. Verificar si la ruta FORZA autenticación (override de rutas públicas)
        if ($this->isForceAuthRoute($path)) {
            throw new HttpException(401, 'Authentication required for this endpoint.');
        }

        // 2. Verificar si la ruta es pública (permite acceso sin auth)
        if ($this->isPublicRoute($path)) {
            // Ruta pública: permitir sin tenant o con tenant limitado vía headers
            $this->setContextFromHeaders($request);
            return $next($request);
        }

        // 3. Ruta protegida sin autenticación: RECHAZAR
        throw new HttpException(401, 'Authentication required.');
    }

    /**
     * Verifica si la ruta está en la lista de rutas públicas.
     */
    private function isPublicRoute(string $path): bool
    {
        $publicRoutes = config('tenant_public_routes.routes', []);
        
        foreach ($publicRoutes as $pattern) {
            if (Str::is($pattern, $path)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Verifica si la ruta FORZA autenticación (override de rutas públicas).
     */
    private function isForceAuthRoute(string $path): bool
    {
        $forceAuthRoutes = config('tenant_public_routes.force_auth_routes', []);
        
        foreach ($forceAuthRoutes as $pattern) {
            if (Str::is($pattern, $path)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Establece contexto limitado desde headers para rutas públicas.
     * 
     * Solo procesa headers permitidos en la whitelist.
     * NO establece userId (siempre null en rutas públicas).
     * Rol siempre es 'guest' (permisos muy limitados).
     */
    private function setContextFromHeaders(Request $request): void
    {
        $allowedHeaders = config('tenant_public_routes.allowed_public_headers', []);
        
        // Solo procesar si los headers están en la whitelist
        if (!in_array('X-Company-ID', $allowedHeaders, true)) {
            return;
        }

        $companyId = $request->header('X-Company-ID');
        $branchId = $request->header('X-Branch-ID');
        $locale = $request->header('X-Locale', 'es-CL');

        if (!$companyId || !Str::isUuid($companyId)) {
            return; // Sin company válido, continuar sin contexto
        }

        $company = \Modules\Companies\Domain\Entities\Company::where('uuid', $companyId)->first();
        
        if (!$company) {
            return; // Company no existe, continuar sin contexto
        }

        $branch = null;
        if ($branchId && Str::isUuid($branchId) && in_array('X-Branch-ID', $allowedHeaders, true)) {
            $branch = \Modules\Branches\Domain\Entities\Branch::where('uuid', $branchId)
                ->where('company_id', $company->id) // SEGURIDAD: branch debe pertenecer a company
                ->first();
        }

        $this->tenantContext->setCompany(
            companyId: $company->id,
            branchId: $branch?->id,
            userId: null, // Siempre null en rutas públicas
            locale: $locale,
            role: 'guest', // Rol muy limitado
            terminalId: null
        );
    }
}
