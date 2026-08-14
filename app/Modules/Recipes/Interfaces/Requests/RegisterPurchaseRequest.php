<?php

namespace Modules\Recipes\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['manager', 'admin', 'cashier']);
    }

    public function rules(): array
    {
        return [
            'purchase_unit_name' => ['required', 'string', 'max:50'],
            'purchase_quantity' => ['required', 'numeric', 'min:0.01'],
            'total_purchase_cost' => ['required', 'numeric', 'min:0'],
            'conversion_factor_to_base' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'purchase_unit_name.required' => 'El nombre de la unidad de compra es requerido.',
            'purchase_quantity.required' => 'La cantidad comprada es requerida.',
            'purchase_quantity.min' => 'La cantidad debe ser mayor a 0.',
            'total_purchase_cost.required' => 'El costo total de la compra es requerido.',
            'total_purchase_cost.min' => 'El costo no puede ser negativo.',
        ];
    }
}
