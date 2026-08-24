<?php

namespace Modules\Catalog\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('price_lists', 'name')
                    ->where('company_id', $this->user()->company_id)
                    ->where('branch_id', $this->user()->branch_id)
                    ->whereNull('deleted_at')
                    ->ignore($this->route('uuid'), 'uuid'),
            ],
            'display_name' => 'nullable|string|max:100',
            'channel_type' => 'nullable|string|max:50',
            'currency' => 'nullable|string|size:3',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
