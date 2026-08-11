<?php

namespace Modules\Orders\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'menu_item_uuid' => $this->menuItem?->uuid,
            'name' => (string) $this->name_snapshot,
            'unit_price' => (float) $this->unit_price_snapshot,
            'quantity' => (int) $this->quantity,
            'subtotal' => (float) $this->subtotal,
            'notes' => $this->notes,
            'modifiers' => $this->whenLoaded('modifiers', fn() => 
                $this->modifiers->map(fn($modifier) => [
                    'original_product_uuid' => $modifier->originalProduct?->uuid,
                    'substitute_product_uuid' => $modifier->substituteProduct?->uuid,
                    'added_product_uuid' => $modifier->addedProduct?->uuid,
                    'price_adjustment' => (float) $modifier->price_adjustment,
                    'reason' => $modifier->reason,
                    'requires_authorization' => $modifier->requires_authorization,
                ])
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
