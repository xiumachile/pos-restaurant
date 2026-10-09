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
            'version' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['draft', 'confirmed', 'preparing', 'ready', 'served', 'paid', 'closed', 'cancelled'])],
            'table_uuid' => ['nullable', 'uuid', 'exists:restaurant_tables,uuid'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'version.required' => 'El campo version es requerido para control de concurrencia.',
            'version.integer' => 'El campo version debe ser un entero.',
            'version.min' => 'El campo version debe ser al menos 1.',
            'status.in' => 'El estado del pedido es inválido.',
            'table_uuid.exists' => 'La mesa especificada no existe.',
            'table_uuid.uuid' => 'El UUID de la mesa es inválido.',
        ];
    }
}
