<?php

namespace Modules\Cashier\Domain\ValueObjects;

enum CardTipHandling: string
{
    case CASH_PAYOUT = 'cash_payout';   // Entregar en efectivo (sale de caja)
    case PAYROLL = 'payroll';           // Acumular para nómina
    case MIXED = 'mixed';               // Mixto
}
