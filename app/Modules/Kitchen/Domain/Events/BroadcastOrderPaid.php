<?php

namespace Modules\Kitchen\Domain\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Orders\Domain\Entities\Order;

/**
 * Evento de broadcast cuando un pedido es pagado.
 * Se emite al canal de garzones y al dashboard de la empresa.
 */
class BroadcastOrderPaid implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('waiters.' . $this->order->branch_id),
            new PrivateChannel('dashboard.' . $this->order->company_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.paid';
    }

    public function broadcastWith(): array
    {
        return [
            'event' => 'order.paid',
            'order_uuid' => $this->order->uuid,
            'order_number' => $this->order->order_number,
            'total' => (float) $this->order->total,
            'table' => $this->order->table ? [
                'uuid' => $this->order->table->uuid,
                'table_number' => $this->order->table->table_number,
            ] : null,
            'cashier' => $this->order->cashier ? [
                'uuid' => $this->order->cashier->uuid,
                'name' => $this->order->cashier->name,
            ] : null,
            'paid_at' => $this->order->paid_at?->toIso8601String(),
        ];
    }
}
