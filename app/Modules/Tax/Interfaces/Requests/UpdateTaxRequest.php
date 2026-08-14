<?php

namespace Modules\Tax\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['manager', 'admin']);
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $taxUuid = $this->route('uuid');

        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'code' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('taxes', 'code')
                    ->where(function ($query) use ($companyId) {
                        return $query->where('company_id', $companyId);
                    })
                    ->ignore($taxUuid, 'uuid'),
            ],
            'type' => ['sometimes', 'string', 'in:percent,fixed,exempt'],
            'rate' => ['sometimes', 'numeric', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Ya existe un impuesto con este código en la empresa.',
            'type.in' => 'El tipo debe ser: percent, fixed o exempt.',
            'rate.min' => 'La tasa no puede ser negativa.',
        ];
    }
}
