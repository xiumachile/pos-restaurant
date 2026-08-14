<?php

namespace Modules\Printers\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrinterStationMappingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'printer_uuid' => $this->printer?->uuid,
            'printer_name' => $this->printer?->name,
            'category_id' => $this->category_id,
            'category_name' => $this->category?->name_translations['es'] ?? null,
            'product_keywords' => $this->product_keywords ?? [],
            'priority' => (int) $this->priority,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
