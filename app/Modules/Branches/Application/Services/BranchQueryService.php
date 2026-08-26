<?php

namespace Modules\Branches\Application\Services;

use Modules\Branches\Domain\Contracts\BranchQueryServiceInterface;
use Modules\Branches\Domain\Entities\Branch;

/**
 * Implementación del servicio de consultas de sucursales.
 * 
 * F1.4b: Esta clase encapsula toda la lógica de consulta a la tabla branches,
 * permitiendo que otros módulos obtengan información sin conocer la estructura interna.
 */
class BranchQueryService implements BranchQueryServiceInterface
{
    public function getTimezoneByBranchId(int $branchId): string
    {
        $timezone = Branch::where('id', $branchId)->value('timezone');
        return $timezone ?: 'America/Santiago';
    }

    public function findById(int $branchId): ?Branch
    {
        return Branch::find($branchId);
    }

    public function findByCode(string $code): ?Branch
    {
        return Branch::where('code', $code)->first();
    }
}

    public function getCompanyIdByBranchId(int $branchId): ?int
    {
        return Branch::where('id', $branchId)->value('company_id');
    }
