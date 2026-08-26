<?php

namespace Modules\Catalog\Domain\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Contrato para exportar datos del catálogo hacia Sync.
 * 
 * F1.4c: Permite que Sync obtenga categorías y productos
 * sin acceder directamente a las tablas.
 */
interface CatalogExportServiceInterface
{
    /**
     * Obtener categorías modificadas desde una fecha.
     */
    public function getChangedCategories(int $branchId, ?Carbon $since): Collection;

    /**
     * Obtener productos modificados desde una fecha.
     */
    public function getChangedProducts(int $branchId, ?Carbon $since): Collection;
}
