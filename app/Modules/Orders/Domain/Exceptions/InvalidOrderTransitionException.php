<?php

namespace Modules\Orders\Domain\Exceptions;

use Exception;
use Modules\Orders\Domain\ValueObjects\OrderStatus;

class InvalidOrderTransitionException extends Exception
{
    public static function fromTo(OrderStatus $from, OrderStatus $to): self
    {
        return new self(
            "Transición inválida de pedido: no se puede pasar de '{$from->value}' a '{$to->value}'.",
            422
        );
    }

    public static function notEditable(): self
    {
        return new self(
            'El pedido no puede ser modificado porque ya fue confirmado.',
            422
        );
    }

    public static function requiresReason(): self
    {
        return new self(
            'La cancelación requiere una razón (cancellation_reason).',
            422
        );
    }
}
