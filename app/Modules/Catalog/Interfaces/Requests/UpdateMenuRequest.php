<?php

namespace Modules\Catalog\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuRequest extends FormRequest
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
                Rule::unique('menus', 'name')
                    ->where('company_id', $this->user()->company_id)
                    ->where('branch_id', $this->user()->branch_id)
                    ->whereNull('deleted_at')
                    ->ignore($this->route('uuid'), 'uuid'),
            ],
            'description' => 'nullable|string|max:500',
            'price_list_id' => [
                'sometimes',
                'string',
                Rule::exists('price_lists', 'uuid')
                    ->where('company_id', $this->user()->company_id)
                    ->whereNull('deleted_at'),
            ],
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
