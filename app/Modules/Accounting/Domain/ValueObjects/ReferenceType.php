<?php

namespace Modules\Accounting\Domain\ValueObjects;

/**
 * Tipos de documento fuente que generan asientos contables.
 */
enum ReferenceType: string
{
    case PAYMENT = 'payment';           // Pago recibido
    case REFUND = 'refund';             // Reembolso
    case PAYOUT = 'payout';             // Retiro de efectivo
    case CASH_OPEN = 'cash_open';       // Apertura de caja
    case CASH_CLOSE = 'cash_close';     // Cierre de caja
    case ADJUSTMENT = 'adjustment';     // Ajuste manual
    case TIP_PAYOUT = 'tip_payout';     // Pago de propinas a garzones
}
