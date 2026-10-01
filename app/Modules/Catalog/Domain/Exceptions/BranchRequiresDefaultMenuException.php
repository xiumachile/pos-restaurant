<?php

namespace Modules\Catalog\Domain\Exceptions;

use Exception;

/**
 * Excepción lanzada cuando se intenta eliminar o desactivar la única carta default de una sucursal.
 * 
 * Regla de negocio: Cada sucursal debe tener al menos una carta marcada como default
 * para garantizar que el endpoint /menus/active siempre pueda resolver una carta
 * cuando no hay reglas de activación activas para el contexto actual.
 */
class BranchRequiresDefaultMenuException extends Exception
{
    public function __construct(string $menuName)
    {
        parent::__construct(
            "No se puede eliminar o desactivar la carta '{$menuName}' porque es la única carta default de la sucursal. " .
            "Marque otra carta como default antes de realizar esta operación."
        );
    }
}
