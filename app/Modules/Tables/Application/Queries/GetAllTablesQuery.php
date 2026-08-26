<?php

namespace Modules\Tables\Application\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Tables\Domain\Entities\RestaurantTable;

/**
 * Query Object: Listar todas las mesas ordenadas.
 * 
 * Responsabilidad:
 * - Encapsular la consulta de listing
 * - Aplicar ordenamiento por defecto
 */
class GetAllTablesQuery
{
    public function execute(): Collection
    {
        return RestaurantTable::query()
            ->ordered()
            ->get();
    }
}
