<?php

namespace Modules\Recipes\Domain\ValueObjects;

/**
 * Dimensión física de un insumo.
 * Según Anexo de Especificación Técnica de Recetas v2.0 - Sección 2.
 */
enum DimensionType: string
{
    case MASS = 'mass';       // Masa (peso) -> unidad base: gramo
    case VOLUME = 'volume';   // Líquido (volumen) -> unidad base: mililitro
    case COUNT = 'count';     // Conteo (piezas) -> unidad base: unidad

    public function label(): string
    {
        return match($this) {
            self::MASS => 'Masa',
            self::VOLUME => 'Volumen',
            self::COUNT => 'Conteo',
        };
    }

    public function labelZh(): string
    {
        return match($this) {
            self::MASS => '质量',
            self::VOLUME => '体积',
            self::COUNT => '计数',
        };
    }

    /**
     * Unidad base SI recomendada para esta dimensión.
     */
    public function baseUnit(): BaseUnit
    {
        return match($this) {
            self::MASS => BaseUnit::GRAM,
            self::VOLUME => BaseUnit::MILLILITER,
            self::COUNT => BaseUnit::UNIT,
        };
    }
}
