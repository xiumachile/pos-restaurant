<?php

namespace Modules\Tables\Domain\Exceptions;

use DomainException;
use Modules\Tables\Domain\ValueObjects\TableStatus;

class InvalidTableStatusTransition extends DomainException
{
    public static function fromTo(TableStatus $from, TableStatus $to): self
    {
        return new self(
            "Transición de estado inválida: no se puede pasar de '{$from->value}' a '{$to->value}'."
        );
    }

    public static function occupyWithoutOrder(): self
    {
        return new self(
            'No se puede ocupar una mesa sin asociarla a un pedido (current_order_id).'
        );
    }
}
