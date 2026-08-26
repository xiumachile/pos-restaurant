<?php

namespace Modules\Branches\Domain\Contracts;

use Modules\Branches\Domain\Entities\Branch;

/**
 * Contrato formal para consultas de sucursales desde otros módulos.
 * 
 * F1.4b + F1.4c: Este contrato permite que módulos como Catalog y Sync
 * consulten información de sucursales sin acceder directamente a la tabla.
 */
interface BranchQueryServiceInterface
{
    /**
     * Obtener la timezone de una sucursal.
     */
    public function getTimezoneByBranchId(int $branchId): string;

    /**
     * Buscar sucursal por ID.
     */
    public function findById(int $branchId): ?Branch;

    /**
     * Obtener sucursal por código.
     */
    public function findByCode(string $code): ?Branch;

    /**
     * Obtener company_id de una sucursal (para Sync).
     */
    public function getCompanyIdByBranchId(int $branchId): ?int;
}
