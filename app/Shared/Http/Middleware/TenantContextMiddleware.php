<?php

namespace App\Shared\Http\Middleware;

use App\Shared\Application\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantContextMiddleware
{
    public function __construct(
        private TenantContext $tenantContext
    ) {}

    /**
     * Establece el contexto de tenant desde el usuario autenticado (auth:api)
     * o desde los headers X-Company-ID y X-Branch-ID.
     *
     * IMPORTANTE: Este middleware debe ejecutarse DESPUÉS de auth:api
     * para que el usuario ya esté autenticado en el guard.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            // Usuario autenticado: usar datos del usuario
            $this->tenantContext->setCompany(
                companyId: $user->company_id,
                branchId: $user->branch_id,
                userId: $user->id,
                locale: $user->locale ?? 'es-CL',
                role: $user->role ?? 'user',
                terminalId: null
            );
        } else {
            // Sin autenticación: intentar leer headers
            $this->setContextFromHeaders($request);
        }

        return $next($request);
    }

    private function setContextFromHeaders(Request $request): void
    {
        $companyId = $request->header('X-Company-ID');
        $branchId = $request->header('X-Branch-ID');
        $locale = $request->header('X-Locale', 'es-CL');

        if ($companyId && Str::isUuid($companyId)) {
            $company = \Modules\Companies\Domain\Entities\Company::where('uuid', $companyId)->first();
            
            if ($company) {
                $branch = null;
                if ($branchId && Str::isUuid($branchId)) {
                    $branch = \Modules\Branches\Domain\Entities\Branch::where('uuid', $branchId)->first();
                }

                $this->tenantContext->setCompany(
                    companyId: $company->id,
                    branchId: $branch?->id,
                    userId: null,
                    locale: $locale,
                    role: 'guest',
                    terminalId: null
                );
            }
        }
    }
}
