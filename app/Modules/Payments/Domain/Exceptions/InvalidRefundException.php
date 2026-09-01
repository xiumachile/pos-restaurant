<?php

namespace Modules\Payments\Domain\Exceptions;

use Exception;

class InvalidRefundException extends Exception
{
    public static function paymentNotRefundable(string $status): self
    {
        return new self("El pago no puede reembolsarse en estado '{$status}'.");
    }

    public static function invalidAmount(float $amount): self
    {
        return new self("El monto del reembolso debe ser positivo. Recibido: {$amount}");
    }

    public static function exceedsPaymentAmount(float $amount, float $paymentAmount): self
    {
        return new self("El reembolso ({$amount}) excede el monto del pago ({$paymentAmount}).");
    }

    public static function exceedsRefundableAmount(float $amount, float $maxRefundable, float $alreadyRefunded): self
    {
        return new self(
            "El reembolso ({$amount}) excede el monto reembolsable ({$maxRefundable}). " .
            "Ya se reembolsaron {$alreadyRefunded}."
        );
    }

    public static function missingAccounts(): self
    {
        return new self("Faltan cuentas contables requeridas (Cash, Revenue) para procesar el reembolso.");
    }
}
