<?php

namespace Modules\Orders\Domain\ValueObjects;

enum OrderType: string
{
    case DINE_IN = 'dine_in';
    case TAKEOUT = 'takeout';
    case DELIVERY = 'delivery';

    public function label(): string
    {
        return match($this) {
            self::DINE_IN => 'Para servir en mesa',
            self::TAKEOUT => 'Para llevar',
            self::DELIVERY => 'Delivery',
        };
    }

    public function requiresTable(): bool
    {
        return $this === self::DINE_IN;
    }
}
