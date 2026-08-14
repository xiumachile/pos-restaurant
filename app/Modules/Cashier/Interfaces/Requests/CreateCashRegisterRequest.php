<?php

namespace Modules\Cashier\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['manager', 'admin']);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'opening_amount_default' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:1'],
            'requires_dual_control' => ['nullable', 'boolean'],
            'printer_id' => ['nullable', 'string', 'max:100'],
            'drawer_serial' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la caja es requerido.',
            'code.required' => 'El código de la caja es requerido.',
            'opening_amount_default.min' => 'El monto de apertura no puede ser negativo.',
            'max_amount.min' => 'El monto máximo debe ser positivo.',
        ];
    }
}
