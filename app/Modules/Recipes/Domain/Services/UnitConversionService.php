<?php

namespace Modules\Recipes\Domain\Services;

use Modules\Recipes\Domain\ValueObjects\BaseUnit;

/**
 * Servicio para conversión entre unidades de medida.
 */
class UnitConversionService
{
    /**
     * Convierte una cantidad de una unidad a otra.
     *
     * @param float $quantity Cantidad a convertir
     * @param BaseUnit $fromUnit Unidad origen
     * @param BaseUnit $toUnit Unidad destino
     * @return float Cantidad convertida
     */
    public function convert(float $quantity, BaseUnit $fromUnit, BaseUnit $toUnit): float
    {
        // Verificar que ambas unidades sean de la misma dimensión
        if ($fromUnit->dimension() !== $toUnit->dimension()) {
            throw new \InvalidArgumentException(
                "No se puede convertir entre dimensiones diferentes: " .
                $fromUnit->dimension()->value . " a " . $toUnit->dimension()->value
            );
        }

        // Convertir a unidad base, luego a unidad destino
        $baseQuantity = $fromUnit->toBase($quantity);
        $toUnitFactor = $toUnit->conversionFactorToBase();

        return round($baseQuantity / $toUnitFactor, 4);
    }

    /**
     * Convierte a unidad base SI.
     */
    public function toBase(float $quantity, BaseUnit $unit): float
    {
        return $unit->toBase($quantity);
    }

    /**
     * Convierte desde unidad base SI a otra unidad.
     */
    public function fromBase(float $baseQuantity, BaseUnit $unit): float
    {
        return round($baseQuantity / $unit->conversionFactorToBase(), 4);
    }
}
