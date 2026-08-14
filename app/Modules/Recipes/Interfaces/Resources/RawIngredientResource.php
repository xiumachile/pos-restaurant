<?php

namespace Modules\Recipes\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RawIngredientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'sku' => $this->sku,
            'name_translations' => $this->name_translations,
            'dimension_type' => $this->dimension_type?->value,
            'base_unit' => $this->base_unit?->value,
            'current_stock_base' => (float) $this->current_stock_base,
            'minimum_stock_base' => (float) $this->minimum_stock_base,
            'cost_per_base_unit' => (float) $this->cost_per_base_unit,
            'total_stock_value' => $this->totalStockValue(),
            'is_active' => $this->is_active,
            'is_low_stock' => (float) $this->current_stock_base < (float) $this->minimum_stock_base,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
