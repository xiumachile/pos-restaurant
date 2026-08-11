<?php

namespace Modules\Identity\Domain\Exceptions;

use Exception;

class InvalidCredentialsException extends Exception
{
    public static function email(): self
    {
        return new self('Las credenciales de email/password son inválidas.', 401);
    }

    public static function pin(): self
    {
        return new self('El PIN proporcionado es inválido.', 401);
    }

    public static function inactive(): self
    {
        return new self('El usuario está desactivado.', 401);
    }
}
