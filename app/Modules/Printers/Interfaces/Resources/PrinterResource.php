<?php

namespace Modules\Printers\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrinterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'connection_type' => $this->connection_type?->value,
            'connection_type_label' => $this->connection_type?->label(),
            'host' => $this->host,
            'port' => $this->port,
            'device_path' => $this->device_path,
            'paper_width' => $this->paper_width,
            'auto_cut' => (bool) $this->auto_cut,
            'open_drawer_on_print' => (bool) $this->open_drawer_on_print,
            'is_active' => (bool) $this->is_active,
            'last_printed_at' => $this->last_printed_at?->toIso8601String(),
            'print_count' => (int) $this->print_count,
            'is_valid_connection' => $this->validateConnection(),
            'is_kitchen_printer' => $this->isKitchenPrinter(),
            'mappings_count' => $this->whenCounted('stationMappings'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
