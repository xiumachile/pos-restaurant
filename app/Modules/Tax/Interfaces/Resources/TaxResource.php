<?php

namespace Modules\Tax\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'rate' => (float) $this->rate,
            'effective_rate' => $this->effectiveRate(),
            'is_percentage' => $this->isPercentage(),
            'is_exempt' => $this->isExempt(),
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'products_count' => $this->whenCounted('products_count'),
            'categories_count' => $this->whenCounted('categories_count'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
