<?php

namespace Modules\Recipes\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para crear un lote de producción.
 */
class CreateProductionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_uuid' => ['required', 'string', 'uuid'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'batch_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_uuid.required' => 'El producto es obligatorio.',
            'product_uuid.uuid' => 'El UUID del producto debe ser válido.',
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.integer' => 'La cantidad debe ser un número entero.',
            'quantity.min' => 'La cantidad debe ser al menos 1.',
            'quantity.max' => 'La cantidad no puede exceder 10000.',
            'batch_notes.max' => 'Las notas no pueden exceder 500 caracteres.',
        ];
    }
}
