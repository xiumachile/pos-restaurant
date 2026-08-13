<?php

namespace Modules\Payments\Domain\ValueObjects;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case REFUNDED = 'refunded';
    case FAILED = 'failed';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pendiente',
            self::COMPLETED => 'Completado',
            self::REFUNDED => 'Reembolsado',
            self::FAILED => 'Fallido',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::REFUNDED, self::FAILED]);
    }

    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }
}
