<?php

namespace Modules\Recipes\Domain\Exceptions;

use Exception;
use Modules\Recipes\Domain\Entities\RawIngredient;

class InsufficientIngredientStockException extends Exception
{
    public function __construct(
        RawIngredient $ingredient,
        float $requested,
        float $available
    ) {
        $name = $ingredient->name_translations['es'] ?? $ingredient->sku;
        parent::__construct(
            "Stock insuficiente de '{$name}'. Solicitado: {$requested}, Disponible: {$available}"
        );
    }
}
