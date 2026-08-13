<?php

namespace Modules\Payments\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'payment_number' => $this->payment_number,
            'method_code' => $this->method_code,
            'amount' => (float) $this->amount,
            'tip_amount' => (float) $this->tip_amount,
            'total_amount' => (float) $this->total_amount,
            'reference_code' => $this->reference_code,
            'status' => $this->status->value,
            'idempotency_key' => $this->idempotency_key,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
