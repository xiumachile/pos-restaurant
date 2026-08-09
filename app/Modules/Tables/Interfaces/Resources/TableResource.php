<?php

namespace Modules\Tables\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'table_number' => $this->table_number,
            'area_code' => $this->area_code,
            'area_name' => $this->area_name, // Uses accessor with i18n
            'capacity' => $this->capacity,
            'status' => $this->status->value,
            'has_active_order' => $this->hasActiveOrder(),
            'current_order_id' => $this->current_order_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     */
    public function with($request): array
    {
        return [];
    }

    /**
     * Wrap the resource in a "data" key.
     */
    public static function collection($resource): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        return parent::collection($resource);
    }
}
