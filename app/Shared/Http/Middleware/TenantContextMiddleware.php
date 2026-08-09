<?php

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Shared\Application\TenantContext;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;

class TenantContextMiddleware
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Intentar obtener desde usuario autenticado (Fase 4 - JWT)
        if (auth()->check()) {
            $user = auth()->user();
            if (isset($user->company_id)) {
                $this->tenantContext->setCompany($user->company_id);
            }
            if (isset($user->branch_id)) {
                $this->tenantContext->setBranch($user->branch_id);
            }
        }

        // 2. Override desde headers (para testing y API)
        $headerCompanyId = $request->header('X-Company-ID');
        $headerBranchId = $request->header('X-Branch-ID');

        if ($headerCompanyId) {
            $company = Company::withoutGlobalScopes()->where('uuid', $headerCompanyId)->first();
            if ($company) {
                $this->tenantContext->setCompany($company->id);
            }
        }

        if ($headerBranchId) {
            $branch = Branch::withoutGlobalScopes()->where('uuid', $headerBranchId)->first();
            if ($branch) {
                $this->tenantContext->setBranch($branch->id);
            }
        }

        return $next($request);
    }
}
