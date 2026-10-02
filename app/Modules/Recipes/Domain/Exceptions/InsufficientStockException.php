<?php

namespace Modules\Recipes\Domain\Exceptions;

use Exception;

/**
 * Excepción lanzada cuando se intenta realizar un movimiento de salida
 * sin stock suficiente en el insumo.
 */
class InsufficientStockException extends Exception
{
    public function __construct(
        public readonly int $rawIngredientId,
        public readonly float $requested,
        public readonly float $available
    ) {
        parent::__construct(
            "Insufficient stock for ingredient {$rawIngredientId}: " .
            "requested {$requested}, available {$available}"
        );
    }
}
