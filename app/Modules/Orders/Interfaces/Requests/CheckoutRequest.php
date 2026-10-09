<?php

namespace Modules\Orders\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method_uuid' => ['required', 'uuid', 'exists:payment_methods,uuid'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
            'reference_code' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method_uuid.required' => 'El método de pago es requerido.',
            'payment_method_uuid.uuid' => 'El método de pago debe ser un UUID válido.',
            'payment_method_uuid.exists' => 'El método de pago no existe.',
            'tip_amount.numeric' => 'La propina debe ser un número.',
            'tip_amount.min' => 'La propina no puede ser negativa.',
            'reference_code.max' => 'El código de referencia no puede exceder 100 caracteres.',
            'notes.max' => 'Las notas no pueden exceder 500 caracteres.',
        ];
    }
}
