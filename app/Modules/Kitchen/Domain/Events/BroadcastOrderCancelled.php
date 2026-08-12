<?php

namespace Modules\Kitchen\Domain\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Orders\Domain\Entities\Order;

/**
 * Evento de broadcast cuando un pedido es cancelado.
 * Se emite al canal de cocina de la sucursal.
 */
class BroadcastOrderCancelled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('kitchen.' . $this->order->branch_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.cancelled';
    }

    public function broadcastWith(): array
    {
        return [
            'event' => 'order.cancelled',
            'order_uuid' => $this->order->uuid,
            'order_number' => $this->order->order_number,
            'table' => $this->order->table ? [
                'uuid' => $this->order->table->uuid,
                'table_number' => $this->order->table->table_number,
            ] : null,
            'cancellation_reason' => $this->order->cancellation_reason,
            'cancelled_at' => $this->order->cancelled_at?->toIso8601String(),
        ];
    }
}
