<?php

namespace Modules\Payments\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'session_number' => $this->session_number,
            'status' => $this->status->value,
            'opening_amount' => (float) $this->opening_amount,
            'closing_amount' => $this->closing_amount ? (float) $this->closing_amount : null,
            'expected_amount' => $this->expected_amount ? (float) $this->expected_amount : null,
            'difference' => $this->difference ? (float) $this->difference : null,
            'opening_notes' => $this->opening_notes,
            'closing_notes' => $this->closing_notes,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'user' => $this->user ? [
                'uuid' => $this->user->uuid,
                'name' => $this->user->name,
            ] : null,
        ];
    }
}
