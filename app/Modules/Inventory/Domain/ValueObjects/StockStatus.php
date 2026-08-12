<?php

namespace Modules\Inventory\Domain\ValueObjects;

enum StockStatus: string
{
    case AVAILABLE = 'available';
    case LOW_STOCK = 'low_stock';
    case OUT_OF_STOCK = 'out_of_stock';

    /**
     * Calcula el estado del stock basado en cantidad actual y mínimo.
     */
    public static function fromQuantity(float $quantity, float $minStock): self
    {
        if ($quantity <= 0) {
            return self::OUT_OF_STOCK;
        }
        if ($quantity <= $minStock) {
            return self::LOW_STOCK;
        }
        return self::AVAILABLE;
    }

    public function label(): string
    {
        return match($this) {
            self::AVAILABLE => 'Disponible',
            self::LOW_STOCK => 'Stock Bajo',
            self::OUT_OF_STOCK => 'Sin Stock',
        };
    }
}
