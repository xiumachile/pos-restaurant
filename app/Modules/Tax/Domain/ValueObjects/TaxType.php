<?php

namespace Modules\Tax\Domain\ValueObjects;

/**
 * Tipo de impuesto.
 * - percent: porcentaje sobre el precio (ej: IVA 19%)
 * - fixed: monto fijo por unidad (ej: $500 por litro)
 * - exempt: exento de impuesto (0%)
 */
enum TaxType: string
{
    case PERCENT = 'percent';
    case FIXED = 'fixed';
    case EXEMPT = 'exempt';

    public function label(): string
    {
        return match($this) {
            self::PERCENT => 'Porcentaje',
            self::FIXED => 'Monto Fijo',
            self::EXEMPT => 'Exento',
        };
    }

    public function labelZh(): string
    {
        return match($this) {
            self::PERCENT => '百分比',
            self::FIXED => '固定金额',
            self::EXEMPT => '免税',
        };
    }

    /**
     * Calcula el impuesto según el tipo.
     * 
     * @param float $baseAmount Monto base (neto)
     * @param float $quantity Cantidad de unidades
     * @param float $rate Tasa (porcentaje o monto fijo)
     * @return float Monto del impuesto
     */
    public function calculate(float $baseAmount, float $quantity, float $rate): float
    {
        return match($this) {
            self::PERCENT => round($baseAmount * ($rate / 100), 2),
            self::FIXED => round($rate * $quantity, 2),
            self::EXEMPT => 0.0,
        };
    }
}
