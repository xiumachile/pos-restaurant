<?php

namespace Modules\Inventory\Domain\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public static function forItem(string $itemName, float $requested, float $available): self
    {
        return new self(
            "Stock insuficiente para '{$itemName}'. Solicitado: {$requested}, Disponible: {$available}"
        );
    }
}
