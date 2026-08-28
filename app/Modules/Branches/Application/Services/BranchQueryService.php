<?php

namespace Modules\Branches\Application\Services;

use App\Shared\Application\TenantContext;
use Modules\Branches\Domain\Contracts\BranchQueryServiceInterface;
use Modules\Branches\Domain\Entities\Branch;

/**
 * Implementación del servicio de consultas de sucursales.
 */
class BranchQueryService implements BranchQueryServiceInterface
{
    public function __construct(
        private TenantContext $tenantContext
    ) {}

    public function getTimezoneByBranchId(int $branchId): string
    {
        $timezone = Branch::where('id', $branchId)->value('timezone');
        return $timezone ?: 'America/Santiago';
    }

    public function findById(int $branchId): ?Branch
    {
        $branch = Branch::find($branchId);
        
        if (!$branch) {
            return null;
        }

        // S1.3: Validación defensiva - verificar que la branch pertenezca al tenant
        if (!$this->tenantContext->hasCompany()) {
            // Sin contexto de tenant (ej: jobs internos), retornar sin validar
            return $branch;
        }

        if ($branch->company_id !== $this->tenantContext->companyId()) {
            return null; // Branch no pertenece al tenant
        }

        return $branch;
    }

    public function findByCode(string $code): ?Branch
    {
        $branch = Branch::where('code', $code)->first();
        
        if (!$branch) {
            return null;
        }

        // S1.3: Validación defensiva - verificar que la branch pertenezca al tenant
        if (!$this->tenantContext->hasCompany()) {
            // Sin contexto de tenant (ej: jobs internos), retornar sin validar
            return $branch;
        }

        if ($branch->company_id !== $this->tenantContext->companyId()) {
            return null; // Branch no pertenece al tenant
        }

        return $branch;
    }

    public function getCompanyIdByBranchId(int $branchId): ?int
    {
        return Branch::where('id', $branchId)->value('company_id');
    }
}