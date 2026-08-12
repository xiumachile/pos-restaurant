<?php

namespace Modules\Orders\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización por rol se refina en el Bloque C
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['dine_in', 'takeout', 'delivery'])],
            'table_uuid' => ['nullable', 'uuid', 'exists:restaurant_tables,uuid'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de pedido es obligatorio.',
            'type.in' => 'El tipo de pedido debe ser dine_in, takeout o delivery.',
            'table_uuid.exists' => 'La mesa especificada no existe.',
            'table_uuid.uuid' => 'El UUID de la mesa es inválido.',
        ];
    }

    /**
     * Valida que los pedidos dine_in tengan mesa.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('type') === 'dine_in' && !$this->input('table_uuid')) {
                $validator->errors()->add('table_uuid', 'Los pedidos dine_in requieren una mesa.');
            }
        });
    }
}
