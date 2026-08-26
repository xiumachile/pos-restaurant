<?php

namespace Modules\Branches\Domain\Contracts;

use Modules\Branches\Domain\Entities\Branch;

/**
 * Contrato formal para consultas de sucursales desde otros módulos.
 * 
 * F1.4b: Este contrato permite que módulos como Catalog consulten
 * información de sucursales sin acceder directamente a la tabla branches.
 * 
 * Principio: "Tell, Don't Ask" - Branches expone servicios de consulta
 * en lugar de permitir acceso directo a sus datos internos.
 */
interface BranchQueryServiceInterface
{
    /**
     * Obtener la timezone de una sucursal.
     * 
     * @param int $branchId ID de la sucursal
     * @return string Timezone (ej: 'America/Santiago') o fallback si no existe
     */
    public function getTimezoneByBranchId(int $branchId): string;

    /**
     * Buscar sucursal por ID.
     * 
     * @param int $branchId ID de la sucursal
     * @return Branch|null Sucursal o null si no existe
     */
    public function findById(int $branchId): ?Branch;

    /**
     * Obtener sucursal por código.
     * 
     * @param string $code Código de la sucursal
     * @return Branch|null Sucursal o null si no existe
     */
    public function findByCode(string $code): ?Branch;
}

    /**
     * Obtener company_id de una sucursal (para Sync).
     */
    public function getCompanyIdByBranchId(int $branchId): ?int;
