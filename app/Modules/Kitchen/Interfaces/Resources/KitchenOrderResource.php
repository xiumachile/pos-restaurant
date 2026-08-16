<?php

namespace Modules\Kitchen\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource optimizado para la vista de cocina.
 * Incluye solo información relevante para los cocineros.
 */
class KitchenOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'order_number' => $this->order_number,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'priority' => $this->priority instanceof \Modules\Orders\Domain\ValueObjects\OrderPriority ? $this->priority->value : ($this->priority ?? 'normal'),
            'table_number' => $this->table?->table_number,
            'table_uuid' => $this->table?->uuid,
            'area_code' => $this->table?->area_code,
            'waiter_name' => $this->waiter?->name,
            'items' => $this->whenLoaded('items', fn() =>
                $this->items->map(fn($item) => [
                    'uuid' => $item->uuid,
                    'name' => $item->name_snapshot,
                    'quantity' => $item->quantity,
                    'notes' => $item->notes,
                    'modifiers' => $item->modifiers->map(fn($m) => [
                        'type' => $m->substitute_product_id ? 'substitution' : 'addition',
                        'reason' => $m->reason,
                    ])->filter()->values(),
                ])
            ),
            'items_count' => $this->items_count ?? $this->items->count(),
            'notes' => $this->notes,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'waiting_minutes' => $this->confirmed_at?->diffInMinutes(now()),
        ];
    }
}
