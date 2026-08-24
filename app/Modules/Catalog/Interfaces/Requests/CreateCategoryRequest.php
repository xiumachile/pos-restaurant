<?php

namespace Modules\Catalog\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_translations' => 'required|array',
            'name_translations.es' => 'required|string|max:100',
            'name_translations.zh' => 'nullable|string|max:100',
            'parent_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'uuid')->where(function ($query) {
                    $query->where('company_id', $this->user()->company_id)
                          ->where('branch_id', $this->user()->branch_id)
                          ->whereNull('deleted_at');
                }),
            ],
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'tax_id' => 'nullable|integer|exists:taxes,id',
        ];
    }
}
