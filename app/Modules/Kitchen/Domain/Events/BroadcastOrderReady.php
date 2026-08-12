<?php

namespace Modules\Kitchen\Domain\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Orders\Domain\Entities\Order;

/**
 * Evento de broadcast cuando un pedido está listo.
 * Se emite al canal de garzones de la sucursal.
 */
class BroadcastOrderReady implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('waiters.' . $this->order->branch_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.ready';
    }

    public function broadcastWith(): array
    {
        return [
            'event' => 'order.ready',
            'order_uuid' => $this->order->uuid,
            'order_number' => $this->order->order_number,
            'type' => $this->order->type->value,
            'status' => $this->order->status->value,
            'table' => $this->order->table ? [
                'uuid' => $this->order->table->uuid,
                'table_number' => $this->order->table->table_number,
                'area_code' => $this->order->table->area_code,
            ] : null,
            'waiter' => $this->order->waiter ? [
                'uuid' => $this->order->waiter->uuid,
                'name' => $this->order->waiter->name,
            ] : null,
            'items_count' => $this->order->items()->count(),
            'ready_at' => now()->toIso8601String(),
        ];
    }
}
