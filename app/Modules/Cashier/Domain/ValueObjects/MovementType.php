<?php

namespace Modules\Cashier\Domain\ValueObjects;

/**
 * Tipo de movimiento de caja.
 * - withdrawal: retiro de efectivo (exceso, llevar a caja fuerte)
 * - deposit: depósito de efectivo (agregar cambio)
 * - adjustment: ajuste por error de conteo o corrección
 */
enum MovementType: string
{
    case WITHDRAWAL = 'withdrawal';
    case DEPOSIT = 'deposit';
    case ADJUSTMENT = 'adjustment';

    public function label(): string
    {
        return match($this) {
            self::WITHDRAWAL => 'Retiro',
            self::DEPOSIT => 'Depósito',
            self::ADJUSTMENT => 'Ajuste',
        };
    }

    public function labelZh(): string
    {
        return match($this) {
            self::WITHDRAWAL => '取款',
            self::DEPOSIT => '存款',
            self::ADJUSTMENT => '调整',
        };
    }

    /**
     * Indica si este tipo requiere autorización para montos grandes.
     */
    public function requiresAuthorization(): bool
    {
        return in_array($this, [self::WITHDRAWAL, self::ADJUSTMENT]);
    }

    /**
     * Signo del movimiento para cálculo de balance.
     * Withdrawal y adjustment reducen caja, deposit aumenta.
     */
    public function balanceSign(): int
    {
        return match($this) {
            self::WITHDRAWAL => -1,
            self::ADJUSTMENT => -1,
            self::DEPOSIT => 1,
        };
    }
}
