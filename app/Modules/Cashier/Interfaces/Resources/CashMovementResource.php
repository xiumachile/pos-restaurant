<?php

namespace Modules\Cashier\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'amount' => is_numeric($this->amount) ? $this->amount + 0 : 0,
            'balance_impact' => $this->balanceImpact(),
            'reason' => $this->reason,
            'notes' => $this->notes,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'balance_after' => is_numeric($this->balance_after) ? $this->balance_after + 0 : 0,
            'user_name' => $this->user?->name,
            'is_authorized' => $this->isAuthorized(),
            'authorizer_name' => $this->authorizer?->name,
            'authorized_at' => $this->authorized_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
