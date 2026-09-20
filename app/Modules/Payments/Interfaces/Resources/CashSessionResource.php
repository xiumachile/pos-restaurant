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
            'opening_amount' => (int) $this->opening_amount,  // ADR-011: integer CLP
            'closing_amount' => $this->closing_amount ? (int) $this->closing_amount : null,  // ADR-011: integer CLP
            'expected_amount' => $this->expected_amount ? (int) $this->expected_amount : null,  // ADR-011: integer CLP
            'difference' => $this->difference ? (int) $this->difference : null,  // ADR-018
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
