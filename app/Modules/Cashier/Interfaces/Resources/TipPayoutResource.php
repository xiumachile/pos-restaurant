<?php

namespace Modules\Cashier\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TipPayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'cash_session_id' => $this->cash_session_id,
            'waiter' => [
                'id' => $this->waiter->id,
                'name' => $this->waiter->name,
                'email' => $this->waiter->email,
            ],
            'processed_by' => [
                'id' => $this->processor->id,
                'name' => $this->processor->name,
            ],
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'policy_type' => $this->policy_type,
            'notes' => $this->notes,
            'is_voided' => $this->is_voided,
            'voided_at' => $this->voided_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
