<?php

namespace Modules\Recipes\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductRecipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Asegurar que la relación product esté cargada
        $product = $this->resource->relationLoaded('product') 
            ? $this->product 
            : \Modules\Catalog\Domain\Entities\Product::find($this->product_id);

        return [
            'uuid' => $this->uuid,
            'product_uuid' => $product?->uuid,
            'product_name' => $product?->name_translations['es'] ?? 'N/A',
            'product_base_price' => (float) ($product?->base_price ?? 0),
            'description' => $this->description,
            'yield_servings' => $this->yield_servings,
            'total_recipe_cost' => (float) $this->total_recipe_cost,
            'food_cost_percentage' => (float) $this->calculateFoodCostPercentage(),
            'gross_margin' => (float) $this->calculateGrossMargin(),
            'items' => RecipeItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
