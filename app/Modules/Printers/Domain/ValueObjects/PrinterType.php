<?php

namespace Modules\Printers\Domain\ValueObjects;

/**
 * Tipo de impresora según su función en el restaurante.
 */
enum PrinterType: string
{
    case KITCHEN = 'kitchen';   // Cocina: comandas de platos
    case BAR = 'bar';           // Bar: comandas de bebidas
    case RECEIPT = 'receipt';   // Caja: tickets de cliente

    public function label(): string
    {
        return match($this) {
            self::KITCHEN => 'Cocina',
            self::BAR => 'Bar',
            self::RECEIPT => 'Recibos',
        };
    }

    public function labelZh(): string
    {
        return match($this) {
            self::KITCHEN => '厨房',
            self::BAR => '酒吧',
            self::RECEIPT => '收据',
        };
    }

    /**
     * Verifica si este tipo imprime comandas (vs tickets).
     */
    public function isKitchenPrinter(): bool
    {
        return in_array($this, [self::KITCHEN, self::BAR]);
    }
}
