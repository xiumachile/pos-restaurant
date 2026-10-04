<?php

namespace Modules\Tables\Application\UseCases;

use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Orders\Domain\Entities\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DeleteTableUseCase
{
    public function execute(string $uuid, int $branchId): bool
    {
        $table = RestaurantTable::where('uuid', $uuid)
            ->where('branch_id', $branchId)
            ->first();

        if (!$table) {
            throw new ModelNotFoundException("Mesa no encontrada");
        }

        // Validar que no tenga pedidos activos
        $activeOrders = Order::where('table_id', $table->id)
            ->whereIn('status', ['draft', 'confirmed', 'preparing', 'ready', 'served'])
            ->count();

        if ($activeOrders > 0) {
            throw new \DomainException(
                "No se puede eliminar la mesa porque tiene {$activeOrders} pedido(s) activo(s). " .
                "Primero debe cerrar o reasignar los pedidos."
            );
        }

        // Soft delete
        return $table->delete();
    }
}
