<?php

namespace Modules\Payments\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->hasHeader('Idempotency-Key')) {
            $this->merge([
                'idempotency_key' => $this->header('Idempotency-Key'),
            ]);
        }
    }

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
            'reference_code.required_if' => 'El código de referencia es requerido para pagos con tarjeta.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $paymentMethodUuid = $this->input('payment_method_uuid');
            $referenceCode = $this->input('reference_code');

            if ($paymentMethodUuid) {
                $paymentMethod = \Modules\Payments\Domain\Entities\PaymentMethod::where('uuid', $paymentMethodUuid)->first();

                if ($paymentMethod && $paymentMethod->requires_reference && empty($referenceCode)) {
                    $validator->errors()->add('reference_code', 'El código de referencia es requerido para este método de pago.');
                }
            }

            $tipAmount = (float) ($this->input('tip_amount') ?? 0);
            if ($tipAmount > 0) {
                $user = $this->user();
                if (!$user->company->hasCapability('can_accept_tips')) {
                    $validator->errors()->add(
                        'tip_amount',
                        'Esta empresa no acepta propinas. La capability can_accept_tips está deshabilitada.'
                    );
                }
            }
        });
    }
}
