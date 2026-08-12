<?php

namespace Modules\Inventory\Domain\ValueObjects;

enum StockMovementType: string
{
    case IN_PURCHASE = 'in_purchase';
    case IN_RETURN = 'in_return';
    case OUT_RESERVATION = 'out_reservation';
    case OUT_CONSUMPTION = 'out_consumption';
    case ADJUSTMENT = 'adjustment';

    /**
     * Verifica si el movimiento incrementa el stock.
     */
    public function isPositive(): bool
    {
        return in_array($this, [
            self::IN_PURCHASE,
            self::IN_RETURN,
        ]);
    }

    /**
     * Verifica si el movimiento decrementa el stock.
     */
    public function isNegative(): bool
    {
        return in_array($this, [
            self::OUT_RESERVATION,
            self::OUT_CONSUMPTION,
        ]);
    }

    /**
     * Calcula el efecto en el stock.
     */
    public function applyToStock(float $currentStock, float $quantity): float
    {
        if ($this->isPositive()) {
            return $currentStock + $quantity;
        }
        if ($this->isNegative()) {
            return $currentStock - $quantity;
        }
        // Adjustment: quantity ya viene con signo
        return $currentStock + $quantity;
    }

    public function label(): string
    {
        return match($this) {
            self::IN_PURCHASE => 'Compra',
            self::IN_RETURN => 'Devolución',
            self::OUT_RESERVATION => 'Reserva',
            self::OUT_CONSUMPTION => 'Consumo',
            self::ADJUSTMENT => 'Ajuste',
        };
    }
}
