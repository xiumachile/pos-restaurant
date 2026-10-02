<?php

namespace Modules\Recipes\Domain\Exceptions;

use Exception;
use Modules\Recipes\Domain\Entities\RawIngredient;

/**
 * Excepción lanzada cuando se intenta descontar más stock del disponible.
 * 
 * Unificada según ADR-022: usada tanto por RawIngredient::deductStock()
 * como por RawIngredientMovement::record().
 */
class InsufficientIngredientStockException extends Exception
{
    public function __construct(
        public readonly RawIngredient $ingredient,
        public readonly float $requested,
        public readonly float $available
    ) {
        $name = $ingredient->name_translations['es'] ?? $ingredient->sku;
        parent::__construct(
            "Stock insuficiente de '{$name}'. Solicitado: {$requested}, Disponible: {$available}"
        );
    }
}
