<?php

namespace Modules\Cashier\Domain\ValueObjects;

/**
 * Denominaciones de efectivo chileno (CLP).
 * Billetes: $20.000, $10.000, $5.000, $2.000, $1.000
 * Monedas: $500, $100, $50, $10, $5, $1
 */
class Denomination
{
    // Billetes CLP (orden descendente)
    public const BILL_20000 = 20000;
    public const BILL_10000 = 10000;
    public const BILL_5000 = 5000;
    public const BILL_2000 = 2000;
    public const BILL_1000 = 1000;

    // Monedas CLP (orden descendente)
    public const COIN_500 = 500;
    public const COIN_100 = 100;
    public const COIN_50 = 50;
    public const COIN_10 = 10;
    public const COIN_5 = 5;
    public const COIN_1 = 1;

    /**
     * Todos los billetes en orden descendente.
     */
    public static function bills(): array
    {
        return [
            self::BILL_20000,
            self::BILL_10000,
            self::BILL_5000,
            self::BILL_2000,
            self::BILL_1000,
        ];
    }

    /**
     * Todas las monedas en orden descendente.
     */
    public static function coins(): array
    {
        return [
            self::COIN_500,
            self::COIN_100,
            self::COIN_50,
            self::COIN_10,
            self::COIN_5,
            self::COIN_1,
        ];
    }

    /**
     * Todas las denominaciones (billetes + monedas).
     */
    public static function all(): array
    {
        return array_merge(self::bills(), self::coins());
    }

    /**
     * Verifica si el valor es un billete.
     */
    public static function isBill(int $value): bool
    {
        return in_array($value, self::bills(), true);
    }

    /**
     * Verifica si el valor es una moneda.
     */
    public static function isCoin(int $value): bool
    {
        return in_array($value, self::coins(), true);
    }

    /**
     * Verifica si el valor es una denominación válida.
     */
    public static function isValid(int $value): bool
    {
        return self::isBill($value) || self::isCoin($value);
    }

    /**
     * Calcula el monto total a partir de un array de conteos.
     * Formato esperado: ['20000' => 5, '10000' => 3, ...]
     */
    public static function calculateTotal(array $counts): float
    {
        $total = 0.0;
        foreach ($counts as $denomination => $quantity) {
            if (self::isValid((int) $denomination) && is_numeric($quantity)) {
                $total += ((int) $denomination) * ((int) $quantity);
            }
        }
        return $total;
    }

    /**
     * Estructura base vacía para un conteo de denominaciones.
     */
    public static function emptyStructure(): array
    {
        $structure = ['bills' => [], 'coins' => []];
        foreach (self::bills() as $bill) {
            $structure['bills'][(string) $bill] = 0;
        }
        foreach (self::coins() as $coin) {
            $structure['coins'][(string) $coin] = 0;
        }
        return $structure;
    }
}
