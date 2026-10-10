<?php

namespace Modules\Orders\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Orders\Domain\Entities\Order;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

/**
 * Se dispara cuando un pedido pasa de confirmed → preparing.
 * 
 * HALLAZGO C-01: Evento de dominio para señalizar inicio de preparación al KDS.
 * Incluye todos los campos requeridos para outbox y deduplicación.
 */
class OrderPreparationStarted
{
    use Dispatchable, SerializesModels;

    public string $event_uuid;
    public string $occurred_at;
    public string $order_uuid;
    public int $company_id;
    public int $branch_id;
    public int $version;

    public function __construct(public Order $order)
    {
        $this->event_uuid = (string) Str::uuid();
        $this->occurred_at = Carbon::now()->toDateTimeString();
        $this->order_uuid = $order->uuid;
        $this->company_id = $order->company_id;
        $this->branch_id = $order->branch_id;
        $this->version = $order->version;
    }
}
