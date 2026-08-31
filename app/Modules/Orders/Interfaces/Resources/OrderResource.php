<?php

namespace Modules\Orders\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'order_number' => $this->order_number,
            'type' => $this->type->value,
            'fulfillment_channel' => $this->fulfillment_channel?->value,
            'fulfillment_channel_label' => $this->fulfillment_channel?->label(),
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'is_editable' => $this->isEditable(),
            'is_active' => $this->isActive(),
            
            // Relaciones
            'table' => $this->when($this->table, fn() => [
                'uuid' => $this->table->uuid,
                'table_number' => $this->table->table_number,
                'area_code' => $this->table->area_code,
            ]),
            'waiter' => $this->when($this->waiter, fn() => [
                'uuid' => $this->waiter->uuid,
                'name' => $this->waiter->name,
            ]),
            'cashier' => $this->when($this->cashier, fn() => [
                'uuid' => $this->cashier->uuid,
                'name' => $this->cashier->name,
            ]),
            
            // Items
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->when($this->items_count !== null, fn() => $this->items_count),
            
            // Totales
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'discount_amount' => (float) $this->discount_amount,
            'total' => (float) $this->total,
            
            // Notas
            'notes' => $this->notes,
            // Campos de fulfillment (Fase 2 — Order Core sin Mesa)
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'pickup_at' => $this->pickup_at?->toIso8601String(),
            'delivery_address' => $this->delivery_address,
            'delivery_notes' => $this->delivery_notes,
            
            // Timestamps de estado
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'served_at' => $this->served_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            
            // Metadata
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
