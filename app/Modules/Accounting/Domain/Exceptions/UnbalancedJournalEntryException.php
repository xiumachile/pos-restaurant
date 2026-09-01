<?php

namespace Modules\Accounting\Domain\Exceptions;

use Exception;

class UnbalancedJournalEntryException extends Exception
{
    public static function create(float $debits, float $credits): self
    {
        return new self(
            "El asiento contable no está balanceado. Débitos: {$debits}, Créditos: {$credits}. " .
            "La diferencia debe ser cero."
        );
    }

    public static function invalidLine(): self
    {
        return new self(
            "Cada línea del asiento debe tener SOLO débito O SOLO crédito, no ambos."
        );
    }

    public static function emptyEntry(): self
    {
        return new self(
            "El asiento contable debe tener al menos una línea."
        );
    }
}
