<?php

namespace Modules\Recipes\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formato JSON de un movimiento de insumo.
 */
class RawIngredientMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'quantity_base' => (float) $this->quantity_base,
            'balance_after' => (float) $this->balance_after,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'user_id' => $this->user_id,
            'reason' => $this->reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
