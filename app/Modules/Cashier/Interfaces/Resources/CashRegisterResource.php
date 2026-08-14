<?php

namespace Modules\Cashier\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashRegisterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'opening_amount_default' => (float) $this->opening_amount_default,
            'max_amount' => (float) $this->max_amount,
            'requires_dual_control' => (bool) $this->requires_dual_control,
            'printer_id' => $this->printer_id,
            'drawer_serial' => $this->drawer_serial,
            'is_active' => (bool) $this->is_active,
            'is_available' => $this->isAvailable(),
            'is_busy' => $this->isBusy(),
            'current_session_uuid' => $this->currentSession()?->uuid,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
