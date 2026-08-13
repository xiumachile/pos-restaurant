<?php

namespace Modules\Payments\Domain\ValueObjects;

enum CashSessionStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match($this) {
            self::OPEN => 'Abierta',
            self::CLOSED => 'Cerrada',
            self::SUSPENDED => 'Suspendida',
        };
    }

    public function isActive(): bool
    {
        return $this === self::OPEN;
    }

    public function canReceivePayments(): bool
    {
        return $this === self::OPEN;
    }
}
