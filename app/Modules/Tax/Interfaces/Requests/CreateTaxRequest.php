<?php

namespace Modules\Tax\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo manager o admin pueden crear impuestos
        return in_array($this->user()->role, ['manager', 'admin']);
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('taxes', 'code')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId);
                }),
            ],
            'type' => ['required', 'string', 'in:percent,fixed,exempt'],
            'rate' => ['required', 'numeric', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del impuesto es requerido.',
            'code.required' => 'El código del impuesto es requerido.',
            'code.unique' => 'Ya existe un impuesto con este código en la empresa.',
            'type.required' => 'El tipo de impuesto es requerido.',
            'type.in' => 'El tipo debe ser: percent, fixed o exempt.',
            'rate.required' => 'La tasa del impuesto es requerida.',
            'rate.min' => 'La tasa no puede ser negativa.',
        ];
    }
}
