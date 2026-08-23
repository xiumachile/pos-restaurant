<?php

namespace Modules\Orders\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['dine_in', 'takeout', 'delivery'])],
            'table_uuid' => ['nullable', 'uuid', 'exists:restaurant_tables,uuid'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['draft', 'confirmed', 'preparing', 'ready', 'served'])],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de pedido es obligatorio.',
            'type.in' => 'El tipo de pedido debe ser dine_in, takeout o delivery.',
            'table_uuid.exists' => 'La mesa especificada no existe.',
            'table_uuid.uuid' => 'El UUID de la mesa es inválido.',
            'status.in' => 'El estado del pedido debe ser draft, confirmed, preparing, ready o served.',
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
