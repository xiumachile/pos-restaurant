<?php

namespace Modules\Payments\Domain\ValueObjects;

/**
 * Estados de un reembolso.
 */
enum RefundStatus: string
{
    case PENDING = 'pending';       // En proceso
    case COMPLETED = 'completed';   // Completado exitosamente
    case FAILED = 'failed';         // Falló (ej: gateway rechazó)
    case CANCELLED = 'cancelled';   // Cancelado antes de procesar

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pendiente',
            self::COMPLETED => 'Completado',
            self::FAILED => 'Fallido',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::CANCELLED]);
    }
}
