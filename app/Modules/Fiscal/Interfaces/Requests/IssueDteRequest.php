<?php

namespace Modules\Fiscal\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueDteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['manager', 'admin', 'cashier']);
    }

    public function rules(): array
    {
        return [
            'order_uuid' => ['required', 'uuid', 'exists:orders,uuid'],
            'receiver_rut' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{7,8}-[0-9Kk]$/'],
            'receiver_business_name' => ['nullable', 'string', 'max:200', 'required_with:receiver_rut'],
            'environment' => ['nullable', 'string', 'in:certification,production'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_uuid.required' => 'El UUID del pedido es requerido.',
            'order_uuid.exists' => 'El pedido no existe.',
            'receiver_rut.regex' => 'El RUT debe tener formato válido (ej: 76123456-7).',
            'receiver_business_name.required_with' => 'La razón social es requerida cuando hay RUT.',
            'environment.in' => 'El ambiente debe ser certification o production.',
        ];
    }
}
