<?php

namespace Modules\Payments\Domain\ValueObjects;

enum BillType: string
{
    case EQUAL_SPLIT = 'equal_split';
    case BY_ITEMS = 'by_items';
    case CUSTOM_AMOUNT = 'custom_amount';

    public function label(): string
    {
        return match($this) {
            self::EQUAL_SPLIT => 'Partes Iguales',
            self::BY_ITEMS => 'Por Ítems',
            self::CUSTOM_AMOUNT => 'Monto Personalizado',
        };
    }

    /**
     * Verifica si este tipo de bill requiere lista de ítems.
     */
    public function requiresItemIds(): bool
    {
        return $this === self::BY_ITEMS;
    }
}
