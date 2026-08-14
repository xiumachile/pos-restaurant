<?php

namespace Modules\Cashier\Domain\ValueObjects;

/**
 * Tipo de conteo/arqueo de caja.
 * - opening: arqueo de apertura
 * - closing: arqueo de cierre
 * - partial: arqueo parcial (mitad de turno)
 * - audit: arqueo de auditoría
 */
enum CashCountType: string
{
    case OPENING = 'opening';
    case CLOSING = 'closing';
    case PARTIAL = 'partial';
    case AUDIT = 'audit';

    public function label(): string
    {
        return match($this) {
            self::OPENING => 'Apertura',
            self::CLOSING => 'Cierre',
            self::PARTIAL => 'Parcial',
            self::AUDIT => 'Auditoría',
        };
    }
}
