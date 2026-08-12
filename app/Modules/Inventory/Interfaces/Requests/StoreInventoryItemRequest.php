<?php

namespace Modules\Inventory\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            'sku' => ['nullable', 'string', 'max:100'],
            'name_translations' => ['required', 'array'],
            'name_translations.es' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'in:unit,kg,lt'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name_translations.required' => 'Las traducciones del nombre son requeridas.',
            'name_translations.es.required' => 'El nombre en español es requerido.',
            'unit.in' => 'La unidad debe ser: unit, kg o lt.',
        ];
    }
}
