<?php

namespace Modules\Cashier\Domain\ValueObjects;

enum TipPolicyType: string
{
    case WAITER_KEEPS = 'waiter_keeps';
    case SHARED_POOL = 'shared_pool';
    case PERCENTAGE_SPLIT = 'percentage_split';

    public function label(): string
    {
        return match ($this) {
            self::WAITER_KEEPS => 'Propina íntegra al garzón',
            self::SHARED_POOL => 'Pozo común repartido',
            self::PERCENTAGE_SPLIT => 'Reparto porcentual',
        };
    }
}
