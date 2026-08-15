<?php

namespace Modules\Cashier\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Payments\Domain\Entities\CashSession;

/**
 * Se dispara cuando se abre una sesión de caja (CashSession).
 * Nota: El evento usa CashSession porque es la entidad real del dominio
 * (no CashRegister que es el hardware).
 */
class DrawerOpened
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CashSession $session,
        public string $reason
    ) {}
}
