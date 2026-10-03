<?php

namespace Modules\Recipes\Domain\ValueObjects;

/**
 * Tipo de movimiento de stock de insumos.
 * 
 * Según ADR-022: unifica los tipos de movimiento del antiguo Inventory
 * con los movimientos de consumo de recetas.
 */
enum MovementType: string
{
    case InPurchase = 'in_purchase';           // Compra de insumo
    case InProduction = 'in_production';       // Producción interna (ej: salsa elaborada)
    case OutConsumption = 'out_consumption';   // Consumo por pedido/receta
    case OutWaste = 'out_waste';               // Merma/desperdicio
    case Adjustment = 'adjustment';            // Ajuste manual (positivo o negativo)

    public function label(): string
    {
        return match($this) {
            self::InPurchase => 'Compra',
            self::InProduction => 'Producción',
            self::OutConsumption => 'Consumo',
            self::OutWaste => 'Merma',
            self::Adjustment => 'Ajuste',
        };
    }

    /**
     * Determina si el movimiento agrega o resta stock.
     */
    public function affectsStock(float $quantity): float
    {
        return match($this) {
            self::InPurchase, self::InProduction => abs($quantity),
            self::OutConsumption, self::OutWaste => -abs($quantity),
            self::Adjustment => $quantity, // Puede ser positivo o negativo
        };
    }
}
