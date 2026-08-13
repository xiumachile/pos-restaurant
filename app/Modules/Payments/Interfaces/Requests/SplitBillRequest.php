<?php

namespace Modules\Payments\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SplitBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['waiter', 'cashier', 'admin', 'manager']);
    }

    public function rules(): array
    {
        $type = $this->input('type');
        $rules = [
            'type' => ['required', 'string', 'in:equal_split,by_items,custom_amount'],
        ];

        if ($type === 'equal_split') {
            $rules['parts'] = ['required', 'integer', 'min:2', 'max:50'];
        } elseif ($type === 'by_items') {
            $rules['groups'] = ['required', 'array', 'min:1'];
            $rules['groups.*.item_ids'] = ['required', 'array', 'min:1'];
            $rules['groups.*.item_ids.*'] = ['integer'];
            $rules['groups.*.guest_count'] = ['nullable', 'integer', 'min:1'];
        } elseif ($type === 'custom_amount') {
            $rules['amounts'] = ['required', 'array', 'min:1'];
            $rules['amounts.*'] = ['numeric', 'min:0.01'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de split es requerido.',
            'type.in' => 'El tipo debe ser: equal_split, by_items o custom_amount.',
            'parts.required' => 'El numero de partes es requerido para equal_split.',
            'parts.min' => 'Se requieren al menos 2 partes.',
            'groups.required' => 'Los grupos son requeridos para by_items.',
            'amounts.required' => 'Los montos son requeridos para custom_amount.',
        ];
    }
}
