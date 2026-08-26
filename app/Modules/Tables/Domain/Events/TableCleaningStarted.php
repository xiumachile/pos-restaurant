<?php

namespace Modules\Tables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Tables\Domain\Entities\RestaurantTable;

/**
 * Emitido cuando una mesa entra en estado de limpieza.
 * Transición: CLOSED → CLEANING
 *
 * Receptores esperados:
 * - Staff: notifica al personal de limpieza
 * - Analytics: registra tiempo de turnover
 */
class TableCleaningStarted
{
    use Dispatchable;

    public function __construct(
        public RestaurantTable $table,
        public ?string $reason = null
    ) {
    }
}
