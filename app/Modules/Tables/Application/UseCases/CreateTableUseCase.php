<?php

namespace Modules\Tables\Application\UseCases;

use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\ValueObjects\TableStatus;

/**
 * Caso de uso: Crear una nueva mesa.
 * 
 * Responsabilidad:
 * - Validar datos de entrada (ya validados por StoreTableRequest)
 * - Crear la entidad con estado inicial AVAILABLE
 * - Persistir
 */
class CreateTableUseCase
{
    public function execute(array $data): RestaurantTable
    {
        $table = RestaurantTable::create([
            'company_id' => $data['company_id'],
            'branch_id' => $data['branch_id'],
            'area_code' => $data['area_code'],
            'area_name_translations' => $data['area_name_translations'] ?? [],
            'table_number' => $data['table_number'],
            'capacity' => $data['capacity'],
            'status' => TableStatus::Available->value,
        ]);

        return $table;
    }
}
