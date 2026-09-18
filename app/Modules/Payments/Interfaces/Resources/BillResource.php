<?php

namespace Modules\Payments\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'bill_number' => $this->bill_number,
            'type' => $this->type->value,
            'subtotal' => (int) $this->subtotal,  // ADR-011: integer CLP
            'tax_amount' => (int) $this->tax_amount,  // ADR-011: integer CLP
            'discount_amount' => (int) $this->discount_amount,  // ADR-011: integer CLP
            'tip_amount' => (int) $this->tip_amount,  // ADR-011: integer CLP
            'total' => (int) $this->total,  // ADR-011: integer CLP
            'paid_amount' => (int) $this->paid_amount,  // ADR-011: integer CLP
            'remaining_amount' => (int) $this->remaining_amount,  // ADR-011: integer CLP
            'status' => $this->status->value,
            'guest_count' => $this->guest_count,
            'item_ids' => $this->item_ids,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
