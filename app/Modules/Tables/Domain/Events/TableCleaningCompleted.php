<?php

namespace Modules\Tables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Tables\Domain\Entities\RestaurantTable;

/**
 * Emitido cuando la mesa vuelve a disponible después de limpieza.
 * Transición: CLEANING → AVAILABLE
 *
 * Receptores esperados:
 * - Waiter: sabe que puede asignar clientes
 * - Analytics: registra tiempo total de turnover
 */
class TableCleaningCompleted
{
    use Dispatchable;

    public function __construct(
        public RestaurantTable $table
    ) {
    }
}
