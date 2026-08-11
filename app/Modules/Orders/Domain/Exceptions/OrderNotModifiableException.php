<?php

namespace Modules\Orders\Domain\Exceptions;

use Exception;

class OrderNotModifiableException extends Exception
{
    public static function alreadyConfirmed(): self
    {
        return new self(
            'No se pueden modificar los items de un pedido ya confirmado.',
            422
        );
    }

    public static function alreadyClosed(): self
    {
        return new self(
            'No se pueden modificar los items de un pedido cerrado.',
            422
        );
    }
}
