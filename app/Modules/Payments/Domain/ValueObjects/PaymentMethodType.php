<?php

namespace Modules\Payments\Domain\ValueObjects;

enum PaymentMethodType: string
{
    case CASH = 'cash';
    case CARD = 'card';
    case TRANSFER = 'transfer';
    case GIFT_CARD = 'gift_card';
    case OTHER = 'other';

    public function label(): string
    {
        return match($this) {
            self::CASH => 'Efectivo',
            self::CARD => 'Tarjeta',
            self::TRANSFER => 'Transferencia',
            self::GIFT_CARD => 'Tarjeta de Regalo',
            self::OTHER => 'Otro',
        };
    }

    /**
     * Verifica si el método requiere referencia (ej: últimos 4 dígitos de tarjeta).
     */
    public function requiresReference(): bool
    {
        return in_array($this, [self::CARD, self::TRANSFER, self::GIFT_CARD]);
    }

    /**
     * Verifica si el método afecta el conteo de efectivo en caja.
     */
    public function affectsCashDrawer(): bool
    {
        return $this === self::CASH;
    }
}
