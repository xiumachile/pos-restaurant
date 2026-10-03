<?php

namespace Modules\Recipes\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formato JSON de un lote de producción creado.
 */
class ProductionBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->resource['uuid'],
            'product_uuid' => $this->resource['product_uuid'],
            'product_name' => $this->resource['product_name'],
            'quantity' => $this->resource['quantity'],
            'movements_count' => $this->resource['movements_count'],
            'movements' => $this->resource['movements'],
            'batch_notes' => $this->resource['batch_notes'] ?? null,
            'created_at' => $this->resource['created_at'],
        ];
    }
}
