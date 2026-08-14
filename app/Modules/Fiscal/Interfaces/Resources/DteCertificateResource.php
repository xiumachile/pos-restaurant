<?php

namespace Modules\Fiscal\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DteCertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'serial_number' => $this->serial_number,
            'issuer' => $this->issuer,
            'holder_rut' => $this->holder_rut,
            'holder_name' => $this->holder_name,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'days_until_expiration' => $this->daysUntilExpiration(),
            'is_valid' => $this->isValid(),
            'is_expiring_soon' => $this->isExpiringSoon(),
            'environment' => $this->environment,
            'is_active' => (bool) $this->is_active,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
