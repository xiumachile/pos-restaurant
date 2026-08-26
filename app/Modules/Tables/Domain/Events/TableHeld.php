<?php

namespace Modules\Tables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Tables\Domain\Entities\RestaurantTable;

/**
 * Emitido cuando una mesa es reservada temporalmente.
 * Transición: AVAILABLE → HELD
 *
 * Receptores esperados:
 * - Host/Recepción: gestiona esperas
 * - Notifications: alerta si expira el hold
 */
class TableHeld
{
    use Dispatchable;

    public function __construct(
        public RestaurantTable $table,
        public ?string $customerName = null,
        public ?int $holdMinutes = 15
    ) {
    }
}
