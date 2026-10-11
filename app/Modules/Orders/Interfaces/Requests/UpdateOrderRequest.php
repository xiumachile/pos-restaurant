<?php

namespace Modules\Orders\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['draft', 'confirmed', 'preparing', 'ready', 'served', 'paid', 'closed', 'cancelled'])],
            'table_uuid' => ['nullable', 'uuid', 'exists:restaurant_tables,uuid'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:50'],
            // UX-03: Permitir actualización de items en estado draft
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required_with:items', 'numeric'], // numeric acepta int y string numérico
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'El estado del pedido es inválido.',
            'table_uuid.exists' => 'La mesa especificada no existe.',
            'table_uuid.uuid' => 'El UUID de la mesa es inválido.',
        ];
    }
}
