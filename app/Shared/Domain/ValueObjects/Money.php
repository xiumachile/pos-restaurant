<?php

namespace App\Shared\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object para representar dinero con precisión exacta.
 * 
 * ADR-010: Usar enteros internamente (centavos) para evitar
 * errores de punto flotante en operaciones aritméticas.
 * 
 * Conversión:
 * - Input: float (ej: 10000.50)
 * - Interno: integer (ej: 1000050 centavos)
 * - Output: float (ej: 10000.50)
 */
final class Money
{
    private int $cents;
    private string $currency;

    private function __construct(int $cents, string $currency = 'CLP')
    {
        $this->cents = $cents;
        $this->currency = $currency;
    }

    /**
     * Crear Money desde cantidad en unidades (pesos, dólares, etc.)
     */
    public static function fromAmount(float $amount, string $currency = 'CLP'): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    /**
     * Crear Money desde centavos (uso interno)
     */
    public static function fromCents(int $cents, string $currency = 'CLP'): self
    {
        return new self($cents, $currency);
    }

    /**
     * Obtener cantidad en unidades (pesos)
     */
    public function getAmount(): float
    {
        return $this->cents / 100;
    }

    /**
     * Obtener cantidad en centavos (uso interno)
     */
    public function getCents(): int
    {
        return $this->cents;
    }

    /**
     * Sumar dos cantidades de dinero
     */
    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->cents + $other->cents, $this->currency);
    }

    /**
     * Restar cantidad de dinero
     */
    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->cents - $other->cents, $this->currency);
    }

    /**
     * Multiplicar por factor (para cálculos de IVA, descuentos, etc.)
     */
    public function multiply(float $factor): self
    {
        return new self((int) round($this->cents * $factor), $this->currency);
    }

    /**
     * Dividir por divisor
     */
    public function divide(float $divisor): self
    {
        if ($divisor === 0) {
            throw new InvalidArgumentException('Division by zero');
        }
        return new self((int) round($this->cents / $divisor), $this->currency);
    }

    /**
     * Comparar igualdad
     */
    public function equals(Money $other): bool
    {
        return $this->cents === $other->cents && $this->currency === $other->currency;
    }

    /**
     * Comparar mayor que
     */
    public function greaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->cents > $other->cents;
    }

    /**
     * Comparar menor que
     */
    public function lessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->cents < $other->cents;
    }

    /**
     * Verificar si es cero
     */
    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    /**
     * Verificar si es negativo
     */
    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    /**
     * Obtener valor absoluto
     */
    public function absolute(): self
    {
        return new self(abs($this->cents), $this->currency);
    }

    /**
     * Formatear como string (ej: "$10,000.50")
     */
    public function format(): string
    {
        return '$' . number_format($this->getAmount(), 2, ',', '.');
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot operate on different currencies: {$this->currency} vs {$other->currency}"
            );
        }
    }
}
