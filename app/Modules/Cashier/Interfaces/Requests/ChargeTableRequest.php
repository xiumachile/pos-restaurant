<?php

namespace Modules\Cashier\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para cobrar una mesa completa.
 */
class ChargeTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method_uuid' => ['required', 'uuid', 'exists:payment_methods,uuid'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
            'reference_code' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
