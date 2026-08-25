<?php

namespace Modules\Catalog\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Domain\Entities\MenuActivation;

class UpsertMenuActivationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activations' => 'present|array',
            'activations.*.channel_type' => [
                'required',
                'string',
                Rule::in([
                    MenuActivation::CHANNEL_ALL,
                    MenuActivation::CHANNEL_DINE_IN,
                    MenuActivation::CHANNEL_DELIVERY,
                    MenuActivation::CHANNEL_UBER_EATS,
                    MenuActivation::CHANNEL_RAPPI,
                ]),
            ],
            'activations.*.days_of_week' => 'nullable|array|min:1|max:7',
            'activations.*.days_of_week.*' => 'integer|between:1,7',
            'activations.*.time_from' => 'nullable|date_format:H:i',
            'activations.*.time_to' => 'nullable|date_format:H:i',
            'activations.*.priority' => 'integer|min:1',
            'activations.*.is_active' => 'boolean',
        ];
    }
}
