<?php

namespace Modules\Cashier\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['cashier', 'manager', 'admin']);
    }

    public function rules(): array
    {
        return [
            'session_uuid' => ['required', 'uuid', 'exists:cash_sessions,uuid'],
            'type' => ['required', 'string', 'in:withdrawal,deposit,adjustment'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'authorizer_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
            'reference_type' => ['nullable', 'string', 'max:50'],
            'reference_id' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'session_uuid.required' => 'El UUID de la sesión es requerido.',
            'type.required' => 'El tipo de movimiento es requerido.',
            'type.in' => 'El tipo debe ser: withdrawal, deposit o adjustment.',
            'amount.required' => 'El monto es requerido.',
            'amount.min' => 'El monto debe ser mayor a 0.',
            'reason.required' => 'La razón del movimiento es requerida.',
        ];
    }
}
