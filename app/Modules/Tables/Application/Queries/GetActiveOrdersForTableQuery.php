<?php

namespace Modules\Tables\Application\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Orders\Domain\Entities\Order;
use Modules\Tables\Domain\Entities\RestaurantTable;

/**
 * Query Object: Obtener pedidos activos de una mesa.
 * 
 * Responsabilidad:
 * - Encapsular la lógica de consulta compleja
 * - Definir qué estados son "activos"
 * - Retornar colección de Order
 * 
 * Estados considerados "en curso":
 * - draft: recién creado
 * - confirmed: confirmado en cocina
 * - preparing: en preparación
 * - ready: listo para servir
 * - served: servido, esperando cobro
 * 
 * Estados EXCLUIDOS:
 * - paid, closed, cancelled
 */
class GetActiveOrdersForTableQuery
{
    public function execute(string $tableUuid): Collection
    {
        $table = RestaurantTable::where('uuid', $tableUuid)->firstOrFail();

        return Order::query()
            ->where('table_id', $table->id)
            ->whereNotIn('status', ['paid', 'closed', 'cancelled'])
            ->with(['items', 'waiter'])
            ->orderBy('created_at', 'asc')
            ->get();
    }
}
