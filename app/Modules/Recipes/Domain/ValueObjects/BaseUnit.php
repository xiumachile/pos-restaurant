<?php

namespace Modules\Recipes\Domain\ValueObjects;

/**
 * Unidad base SI (Sistema Internacional) en que se almacena el stock.
 * REGLA DE ORO: Todo insumo se almacena SIEMPRE en su unidad base SI.
 * Las unidades de compra/receta son capas de presentación con factor multiplicador.
 */
enum BaseUnit: string
{
    // Masa
    case GRAM = 'g';              // Unidad base SI para masa
    case KILOGRAM = 'kg';         // 1 kg = 1000 g
    case POUND = 'lb';            // 1 lb = 453.592 g

    // Volumen
    case MILLILITER = 'ml';       // Unidad base SI para volumen
    case LITER = 'l';             // 1 L = 1000 ml

    // Conteo
    case UNIT = 'un';             // Unidad base SI para conteo
    case DOZEN = 'doc';           // 1 doc = 12 un
    case PACK = 'pack';           // Configurable (ej: 1 pack = 24 un)

    public function label(): string
    {
        return match($this) {
            self::GRAM => 'Gramo',
            self::KILOGRAM => 'Kilogramo',
            self::POUND => 'Libra',
            self::MILLILITER => 'Mililitro',
            self::LITER => 'Litro',
            self::UNIT => 'Unidad',
            self::DOZEN => 'Docena',
            self::PACK => 'Pack',
        };
    }

    public function symbol(): string
    {
        return $this->value;
    }

    /**
     * Dimensión física a la que pertenece esta unidad.
     */
    public function dimension(): DimensionType
    {
        return match($this) {
            self::GRAM, self::KILOGRAM, self::POUND => DimensionType::MASS,
            self::MILLILITER, self::LITER => DimensionType::VOLUME,
            self::UNIT, self::DOZEN, self::PACK => DimensionType::COUNT,
        };
    }

    /**
     * Factor de conversión a la unidad base SI.
     * Ej: 1 kg = 1000 g, 1 L = 1000 ml, 1 doc = 12 un
     */
    public function conversionFactorToBase(): float
    {
        return match($this) {
            // Masa (base = gramo)
            self::GRAM => 1.0,
            self::KILOGRAM => 1000.0,
            self::POUND => 453.592,

            // Volumen (base = mililitro)
            self::MILLILITER => 1.0,
            self::LITER => 1000.0,

            // Conteo (base = unidad)
            self::UNIT => 1.0,
            self::DOZEN => 12.0,
            self::PACK => 24.0, // default, configurable por producto
        };
    }

    /**
     * Convierte una cantidad de esta unidad a la unidad base SI.
     */
    public function toBase(float $quantity): float
    {
        return round($quantity * $this->conversionFactorToBase(), 4);
    }
}
