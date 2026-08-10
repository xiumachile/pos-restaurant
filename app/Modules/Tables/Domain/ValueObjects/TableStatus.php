<?php

namespace Modules\Tables\Domain\ValueObjects;

enum TableStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Billing = 'billing';
    case Maintenance = 'maintenance';

    /**
     * Obtiene la etiqueta traducible del estado.
     */
    public function label(): string
    {
        return match ($this) {
            self::Available => 'table.status.available',
            self::Occupied => 'table.status.occupied',
            self::Billing => 'table.status.billing',
            self::Maintenance => 'table.status.maintenance',
        };
    }

    /**
     * Verifica si la mesa está disponible para nuevos pedidos.
     */
    public function isOperative(): bool
    {
        return $this !== self::Maintenance;
    }
}
