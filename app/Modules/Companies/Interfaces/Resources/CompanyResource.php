<?php

namespace Modules\Companies\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'tax_id' => $this->tax_id,
            'legal_name' => $this->legal_name,
            'trade_name' => $this->trade_name,
            'default_locale' => $this->default_locale,
            'fallback_locale' => $this->fallback_locale,
            'effective_locale' => $this->effectiveLocale(),
            'is_active' => $this->is_active,
            'settings' => $this->settings ?? [],
            'capabilities' => $this->whenLoaded('capabilities', function () {
                return $this->capabilities->map(function ($capability) {
                    return [
                        'key' => $capability->capability_key,
                        'is_enabled' => $capability->is_enabled,
                        'settings' => $capability->settings ?? [],
                    ];
                });
            }),
            'enabled_capabilities' => $this->enabledCapabilities(),
            'branches_count' => $this->whenCounted('branches'),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
