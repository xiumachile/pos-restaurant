<?php

namespace Modules\Tables\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiningZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'name_translations' => $this->name_translations,
            'name' => $this->getName(),
            'color' => $this->color,
            'floor_level' => $this->floor_level,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            // tables_count viene de withCount('tables') en el service
            'tables_count' => $this->whenCounted('tables', (int) ($this->tables_count ?? 0)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
