<?php

namespace Modules\Tables\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para el floor plan completo.
 * Incluye todas las zonas y sus mesas con posiciones.
 */
class FloorPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'zones' => DiningZoneResource::collection($this->resource['zones']),
            'tables' => $this->resource['tables']->map(function ($table) {
                return [
                    'uuid' => $table->uuid,
                    'table_number' => $table->table_number,
                    'zone_id' => $table->zone_id,
                    'zone_uuid' => $table->zone?->uuid,
                    'capacity' => $table->capacity,
                    'status' => $table->status->value,
                    'has_active_order' => $table->hasActiveOrder(),
                    'current_order_id' => $table->current_order_id,
                    'position_x' => $table->position_x,
                    'position_y' => $table->position_y,
                    'rotation' => $table->rotation,
                    'shape' => $table->shape->value,
                    'width' => $table->width,
                    'height' => $table->height,
                ];
            }),
        ];
    }
}
