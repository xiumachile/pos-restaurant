<?php

namespace Modules\Fiscal\Domain\Exceptions;

use Exception;
use Modules\Fiscal\Domain\ValueObjects\DteType;

class NoFoliosAvailableException extends Exception
{
    public function __construct(DteType $dteType, int $availableFolios = 0)
    {
        parent::__construct(
            "No hay folios disponibles para {$dteType->label()}. " .
            "Disponibles: {$availableFolios}. Solicite un nuevo CAF al SII."
        );
    }
}
