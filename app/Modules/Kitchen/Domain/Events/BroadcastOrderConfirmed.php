<?php

namespace Modules\Kitchen\Domain\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Orders\Domain\Entities\Order;

/**
 * Evento de broadcast cuando un pedido es confirmado.
 * Se emite al canal de cocina de la sucursal.
 */
class BroadcastOrderConfirmed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    /**
     * Canales donde se emitirá el evento.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('kitchen.' . $this->order->branch_id),
        ];
    }

    /**
     * Nombre del evento en el frontend.
     */
    public function broadcastAs(): string
    {
        return 'order.confirmed';
    }

    /**
     * Datos a emitir.
     */
    public function broadcastWith(): array
    {
        return [
            'event' => 'order.confirmed',
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
            'notes' => $this->order->notes,
            'confirmed_at' => $this->order->confirmed_at?->toIso8601String(),
        ];
    }
}
