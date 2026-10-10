<?php

namespace Modules\Orders\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Orders\Domain\Entities\Order;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        // HALLAZGO 11: Autorización centralizada en OrderPolicy
        $order = Order::where('uuid', $this->route('uuid') ?? $this->route('order'))->first();
        
        if (!$order) {
            return true; // Dejar que el controlador maneje 404
        }
        
        return Gate::allows('pay', $order);
    }

    public function rules(): array
    {
        return [
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method_uuid' => ['required', 'uuid', 'exists:payment_methods,uuid'],
            'payments.*.amount' => ['required', 'integer', 'min:1'],
            'tip_amount' => ['nullable', 'integer', 'min:0'],
            'customer_rut' => ['nullable', 'string', 'max:20'],
            'dte_type' => ['nullable', Rule::in(['boleta', 'factura'])],
        ];
    }
}
