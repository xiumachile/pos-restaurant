<?php

namespace Modules\Tables\Domain\ValueObjects;

/**
 * Forma visual de la mesa en el floor plan.
 */
enum TableShape: string
{
    case Square = 'square';
    case Round = 'round';
    case Rectangle = 'rectangle';

    public function label(): string
    {
        return match ($this) {
            self::Square => 'Cuadrada',
            self::Round => 'Redonda',
            self::Rectangle => 'Rectangular',
        };
    }
}
