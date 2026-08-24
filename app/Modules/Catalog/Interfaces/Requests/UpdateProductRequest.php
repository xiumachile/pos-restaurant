<?php

namespace Modules\Catalog\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('uuid');
        
        return [
            'sku' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('products', 'sku')
                    ->where('company_id', $this->user()->company_id)
                    ->whereNull('deleted_at')
                    ->ignore($productId, 'uuid'),
            ],
            'name_translations' => 'sometimes|array',
            'name_translations.es' => 'sometimes|string|max:100',
            'name_translations.zh' => 'nullable|string|max:100',
            'description_translations' => 'nullable|array',
            'description_translations.es' => 'nullable|string|max:500',
            'description_translations.zh' => 'nullable|string|max:500',
            'category_id' => [
                'sometimes',
                'string',
                Rule::exists('categories', 'uuid')->where(function ($query) {
                    $query->where('company_id', $this->user()->company_id)
                          ->where('branch_id', $this->user()->branch_id)
                          ->whereNull('deleted_at');
                }),
            ],
            'base_price' => 'sometimes|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'is_combo' => 'boolean',
            'kitchen_zone_id' => 'nullable|integer|exists:kitchen_zones,id',
            'is_active' => 'boolean',
            'tax_id' => 'nullable|integer|exists:taxes,id',
        ];
    }
}
