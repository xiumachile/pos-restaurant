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
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'discount_amount' => (float) $this->discount_amount,
            'tip_amount' => (float) $this->tip_amount,
            'total' => (float) $this->total,
            'paid_amount' => (float) $this->paid_amount,
            'remaining_amount' => (float) $this->remaining_amount,
            'status' => $this->status->value,
            'guest_count' => $this->guest_count,
            'item_ids' => $this->item_ids,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
