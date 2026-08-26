<?php

namespace Modules\Tables\Application\UseCases;

use Modules\Tables\Domain\Entities\RestaurantTable;

/**
 * Caso de uso: Actualizar datos básicos de una mesa.
 * 
 * Responsabilidad:
 * - Buscar la mesa por UUID
 * - Actualizar campos permitidos (no status)
 * - Persistir
 */
class UpdateTableUseCase
{
    public function execute(string $tableUuid, array $data): RestaurantTable
    {
        $table = RestaurantTable::where('uuid', $tableUuid)->firstOrFail();

        $table->update(array_intersect_key($data, array_flip([
            'area_code',
            'area_name_translations',
            'table_number',
            'capacity',
        ])));

        return $table;
    }
}
