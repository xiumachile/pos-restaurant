<?php

namespace Modules\Fiscal\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DteFolioRangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'dte_type' => $this->dte_type?->value,
            'dte_type_label' => $this->dte_type?->label(),
            'folio_initial' => $this->folio_initial,
            'folio_final' => $this->folio_final,
            'folio_current' => $this->folio_current,
            'total_folios' => $this->totalFolios(),
            'available_folios' => $this->availableFolios(),
            'usage_percentage' => $this->usagePercentage(),
            'is_active' => (bool) $this->is_active,
            'is_running_low' => $this->isRunningLow(),
            'is_exhausted' => $this->isExhausted(),
            'authorization_date' => $this->authorization_date?->toDateString(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
