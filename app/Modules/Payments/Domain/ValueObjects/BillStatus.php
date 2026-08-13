<?php

namespace Modules\Payments\Domain\ValueObjects;

enum BillStatus: string
{
    case OPEN = 'open';
    case PARTIAL = 'partial';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::OPEN => 'Abierta',
            self::PARTIAL => 'Parcialmente Pagada',
            self::PAID => 'Pagada',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function isPayable(): bool
    {
        return in_array($this, [self::OPEN, self::PARTIAL]);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::PAID, self::CANCELLED]);
    }
}
