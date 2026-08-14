<?php

namespace Modules\Printers\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePrinterStationMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['manager', 'admin']);
    }

    public function rules(): array
    {
        return [
            'printer_uuid' => ['required', 'uuid', 'exists:printers,uuid'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'product_keywords' => ['nullable', 'array'],
            'product_keywords.*' => ['string', 'max:100'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Debe tener category_id O product_keywords (no ambos nulos)
            if (empty($this->category_id) && empty($this->product_keywords)) {
                $validator->errors()->add(
                    'category_id',
                    'Debe especificar category_id o product_keywords.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'printer_uuid.required' => 'El UUID de la impresora es requerido.',
            'printer_uuid.exists' => 'La impresora no existe.',
            'category_id.exists' => 'La categoría no existe.',
        ];
    }
}
