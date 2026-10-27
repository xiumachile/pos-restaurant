<?php

namespace Modules\Tables\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FloorPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'plan' => $this->resource['plan'] ?? [
                'width' => 2000,
                'height' => 1500,
                'grid_size' => 40,
                'unit' => 'px',
                'version' => 1,
            ],
            'zones' => $this->resource['zones'] ?? [],
            'objects' => $this->resource['objects'] ?? [],
            'available_tables' => $this->resource['available_tables'] ?? [],
            
            // ✅ Claves legacy para compatibilidad con pruebas y frontend antiguo
            'tables' => $this->resource['tables'] ?? [],
            'decorations' => $this->resource['decorations'] ?? [],
        ];
    }
}
