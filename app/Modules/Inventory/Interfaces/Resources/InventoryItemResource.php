<?php

namespace Modules\Inventory\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Inventory\Domain\ValueObjects\StockStatus;

class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Stock para la sucursal del usuario autenticado
        $branchId = $request->user()?->branch_id;
        $stock = $branchId ? $this->stockForBranch($branchId) : 0;
        $status = $branchId ? $this->stockStatusForBranch($branchId) : StockStatus::OUT_OF_STOCK;

        return [
            'uuid' => $this->uuid,
            'sku' => $this->sku,
            'name' => $this->name_translations['es'] ?? null,
            'name_translations' => $this->name_translations,
            'unit' => $this->unit,
            'cost_price' => (float) $this->cost_price,
            'min_stock' => (float) $this->min_stock,
            'is_active' => $this->is_active,
            'stock' => $stock,
            'status' => $status->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
