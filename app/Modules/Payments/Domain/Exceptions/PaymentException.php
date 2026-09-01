<?php

namespace Modules\Payments\Domain\Exceptions;

use Exception;

class PaymentException extends Exception
{
    public static function duplicateIdempotencyKey(string $key): self
    {
        return new self("Pago con Idempotency-Key '{$key}' ya fue procesado.");
    }

    public static function insufficientAmount(float $requested, float $available): self
    {
        return new self("Monto insuficiente. Solicitado: {$requested}, Disponible: {$available}");
    }

    public static function invalidSplitAmount(): self
    {
        return new self("La suma de las divisiones no coincide con el total del pedido.");
    }

    public static function orderNotPayable(): self
    {
        return new self("El pedido no está en un estado que permita pagos.");
    }

    public static function cashSessionNotOpen(): self
    {
        return new self("No hay una sesión de caja abierta para registrar pagos en efectivo.");
    }

    public static function invalidPaymentMethod(): self
    {
        return new self("El método de pago no es válido o está inactivo.");
    }

    public static function splitTotalsMismatch(float $calculated, float $expected): self
    {
        return new self(
            "La suma de las divisiones (\${$calculated}) no coincide con el total del pedido (\${$expected}). Diferencia mayor a $1.",
            422
        );
    }

    /**
     * Excepción cuando hay propinas pendientes de entregar al cerrar caja.
     */
    public static function tipsNotDelivered(float $pending): self
    {
        return new self(
            "Hay \${$pending} en propinas pendientes de entregar. Debes entregar todas las propinas antes de cerrar caja.",
            422
        );
    }

    public static function orderRequiredForLedger(): self
    {
        return new self('El pago requiere un pedido asociado para generar asiento contable.');
    }

    public static function invalidOrderTotal(float $total): self
    {
        return new self("El total del pedido debe ser mayor a cero. Recibido: {$total}");
    }

    public static function ledgerRecordingFailed(string $reason): self
    {
        return new self("No se pudo registrar el asiento contable del pago: {$reason}");
    }

}
