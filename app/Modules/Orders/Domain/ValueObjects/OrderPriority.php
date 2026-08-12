<?php

namespace Modules\Orders\Domain\ValueObjects;

enum OrderPriority: string
{
    case NORMAL = 'normal';
    case RUSH = 'rush';
    case VIP = 'vip';

    public function label(): string
    {
        return match($this) {
            self::NORMAL => 'Normal',
            self::RUSH => 'Urgente',
            self::VIP => 'VIP',
        };
    }

    public function sortWeight(): int
    {
        return match($this) {
            self::VIP => 3,
            self::RUSH => 2,
            self::NORMAL => 1,
        };
    }
}
