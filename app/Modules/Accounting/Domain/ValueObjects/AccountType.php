<?php

namespace Modules\Accounting\Domain\ValueObjects;

/**
 * Tipos de cuenta contable según la ecuación contable:
 * Assets = Liabilities + Equity + (Revenue - Expenses)
 */
enum AccountType: string
{
    case ASSET = 'asset';           // Activos (efectivo, bancos, por cobrar)
    case LIABILITY = 'liability';   // Pasivos (por pagar, impuestos)
    case EQUITY = 'equity';         // Patrimonio
    case REVENUE = 'revenue';       // Ingresos
    case EXPENSE = 'expense';       // Gastos

    /**
     * Indica si el saldo normal de esta cuenta es débito o crédito.
     */
    public function normalBalance(): string
    {
        return match($this) {
            self::ASSET, self::EXPENSE => 'debit',
            self::LIABILITY, self::EQUITY, self::REVENUE => 'credit',
        };
    }

    /**
     * Label en español para UI.
     */
    public function label(): string
    {
        return match($this) {
            self::ASSET => 'Activo',
            self::LIABILITY => 'Pasivo',
            self::EQUITY => 'Patrimonio',
            self::REVENUE => 'Ingreso',
            self::EXPENSE => 'Gasto',
        };
    }
}
