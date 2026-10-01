<?php

namespace Modules\Catalog\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkProductPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prices' => 'required|array|min:1|max:500',
            'prices.*.product_uuid' => [
                'required',
                'string',
                Rule::exists('products', 'uuid')
                    ->where('company_id', $this->user()->company_id)
                    ->whereNull('deleted_at'),
            ],
            'prices.*.price' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'prices.max' => 'No se pueden actualizar más de 500 precios a la vez.',
            'prices.*.price.min' => 'El precio no puede ser negativo.',
        ];
    }
}
