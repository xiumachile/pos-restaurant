<?php

namespace Modules\Tables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Tables\Domain\Entities\RestaurantTable;

/**
 * Emitido cuando una mesa es sacada de servicio (mantenimiento, avería).
 * Transición: cualquier estado → OUT_OF_SERVICE
 *
 * Receptores esperados:
 * - Manager: notificación
 * - Analytics: registra tiempo fuera de servicio
 */
class TableOutOfService
{
    use Dispatchable;

    public function __construct(
        public RestaurantTable $table,
        public string $reason
    ) {
    }
}
