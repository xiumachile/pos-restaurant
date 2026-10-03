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
            'total_purchase_cost' => ['required', 'integer', 'min:0'],
            'conversion_factor_to_base' => ['nullable', 'numeric', 'min:0.01'],

            // Documento contable (opcional pero recomendado)
            'document_type' => ['nullable', 'string', 'in:boleta,factura,factura_exenta,nota_entrada,otro'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'supplier_rut' => ['nullable', 'string', 'max:20'],
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
            'document_type.in' => 'El tipo de documento debe ser: boleta, factura, factura_exenta, nota_entrada u otro.',
            'document_number.max' => 'El número de documento no puede exceder 50 caracteres.',
            'supplier_name.max' => 'El nombre del proveedor no puede exceder 150 caracteres.',
            'supplier_rut.max' => 'El RUT del proveedor no puede exceder 20 caracteres.',
        ];
    }
}
