<?php

namespace Modules\Tables\Application\UseCases;

use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\Exceptions\InvalidTableStatusTransition;
use Modules\Tables\Domain\ValueObjects\TableStatus;

/**
 * Caso de uso: Cambiar estado de una mesa.
 * 
 * Responsabilidad:
 * - Validar que la transición sea válida
 * - Ejecutar el método de dominio correspondiente
 * - Persistir el cambio
 * 
 * Este caso de uso es invocado desde:
 * - RestaurantTableController::updateStatus()
 * - Futuros listeners de eventos (si otros módulos necesitan cambiar estado)
 */
class ChangeTableStatusUseCase
{
    /**
     * @throws InvalidTableStatusTransition
     */
    public function execute(string $tableUuid, string $newStatus, ?int $orderId = null): RestaurantTable
    {
        $table = RestaurantTable::where('uuid', $tableUuid)->firstOrFail();
        $status = TableStatus::from($newStatus);

        // Ejecutar método de dominio según el nuevo estado
        match ($status) {
            TableStatus::Occupied => $table->occupy($orderId ?? 0),
            TableStatus::Billing => $table->requestBilling(),
            TableStatus::Available => $table->hasActiveOrder() ? $table->free() : $table->enable(),
            TableStatus::Maintenance => $table->setMaintenance(),
            default => throw new InvalidTableStatusTransition(
                $table->status->value,
                $status->value,
                "Estado no soportado: {$status->value}"
            ),
        };

        $table->save();

        return $table;
    }
}
