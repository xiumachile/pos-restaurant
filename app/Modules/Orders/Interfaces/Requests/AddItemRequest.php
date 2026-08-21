<?php

namespace Modules\Orders\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'menu_item_uuid' => ['nullable', 'uuid', 'exists:menu_items,uuid'],
            'product_uuid' => ['nullable', 'uuid', 'exists:products,uuid'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_uuid.exists' => 'El producto no existe.',
            'menu_item_uuid.exists' => 'El item del menú no existe.',
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.integer' => 'La cantidad debe ser un número entero.',
            'quantity.min' => 'La cantidad mínima es 1.',
            'quantity.max' => 'La cantidad máxima es 100.',
        ];
    }

    /**
     * Valida que se envíe al menos uno de los identificadores.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->input('menu_item_uuid') && !$this->input('product_uuid')) {
                $validator->errors()->add(
                    'product_uuid',
                    'Debe enviarse menu_item_uuid o product_uuid.'
                );
            }
        });
    }
}
