<?php

namespace Modules\Cashier\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashCountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'reason' => $this->reason,
            'expected_amount' => is_numeric($this->expected_amount) ? $this->expected_amount + 0 : 0,
            'counted_amount' => is_numeric($this->counted_amount) ? $this->counted_amount + 0 : 0,
            'difference' => is_numeric($this->difference) ? $this->difference + 0 : 0,
            'difference_percentage' => $this->discrepancyPercentage(),
            'denominations' => $this->denominations,
            'cash_amount' => is_numeric($this->cash_amount) ? $this->cash_amount + 0 : 0,
            'card_amount' => is_numeric($this->card_amount) ? $this->card_amount + 0 : 0,
            'transfer_amount' => is_numeric($this->transfer_amount) ? $this->transfer_amount + 0 : 0,
            'other_amount' => is_numeric($this->other_amount) ? $this->other_amount + 0 : 0,
            'notes' => $this->notes,
            'has_discrepancy' => (bool) $this->has_discrepancy,
            'is_balanced' => $this->isBalanced(),
            'has_surplus' => $this->hasSurplus(),
            'has_shortage' => $this->hasShortage(),
            'discrepancy_explanation' => $this->discrepancy_explanation,
            'user_name' => $this->user?->name,
            'supervisor_name' => $this->supervisor?->name,
            'supervised_at' => $this->supervised_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
