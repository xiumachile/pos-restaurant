<?php

namespace Modules\Recipes\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'ingredient_uuid' => $this->ingredient?->uuid,
            'ingredient_sku' => $this->ingredient?->sku,
            'ingredient_name' => $this->ingredient?->name_translations['es'] ?? 'N/A',
            'quantity_base_unit' => (float) $this->quantity_base_unit,
            'waste_percentage' => (float) $this->waste_percentage,
            'effective_discount_base_quantity' => (float) $this->effective_discount_base_quantity,
            'calculated_item_cost' => (float) $this->calculated_item_cost,
        ];
    }
}
