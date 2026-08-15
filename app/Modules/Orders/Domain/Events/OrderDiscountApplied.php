<?php

namespace Modules\Orders\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Orders\Domain\Entities\Order;

/**
 * Se dispara cuando se aplica un descuento a un pedido.
 * Usado por AuditOrderEvents para registrar en audit_logs.
 */
class OrderDiscountApplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public float $discountAmount,
        public string $reason
    ) {}
}
