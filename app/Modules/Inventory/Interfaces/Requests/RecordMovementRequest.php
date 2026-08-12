<?php

namespace Modules\Inventory\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            'branch_uuid' => ['required', 'uuid', 'exists:branches,uuid'],
            'type' => ['required', 'string', 'in:in_purchase,in_return,out_reservation,out_consumption,adjustment'],
            'quantity' => ['required', 'numeric'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_uuid.required' => 'La sucursal es requerida.',
            'type.in' => 'El tipo debe ser: in_purchase, in_return, out_reservation, out_consumption o adjustment.',
            'quantity.required' => 'La cantidad es requerida.',
        ];
    }
}
