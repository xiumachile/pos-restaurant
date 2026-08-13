<?php

namespace Modules\Payments\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['cashier', 'admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            'order_uuid' => ['required', 'uuid', 'exists:orders,uuid'],
            'payment_method_uuid' => ['required', 'uuid', 'exists:payment_methods,uuid'],
            'bill_uuid' => ['nullable', 'uuid', 'exists:bills,uuid'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
            'reference_code' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_uuid.required' => 'El UUID del pedido es requerido.',
            'order_uuid.exists' => 'El pedido especificado no existe.',
            'payment_method_uuid.required' => 'El método de pago es requerido.',
            'payment_method_uuid.exists' => 'El método de pago no existe.',
            'bill_uuid.exists' => 'El bill especificado no existe.',
            'amount.required' => 'El monto es requerido.',
            'amount.min' => 'El monto debe ser mayor a 0.',
            'tip_amount.min' => 'La propina no puede ser negativa.',
            'idempotency_key.required' => 'El Idempotency-Key es requerido.',
            'idempotency_key.uuid' => 'El Idempotency-Key debe ser un UUID válido.',
        ];
    }
}
