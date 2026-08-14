<?php

namespace Modules\Cashier\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCashCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['cashier', 'manager', 'admin']);
    }

    public function rules(): array
    {
        return [
            'session_uuid' => ['required', 'uuid', 'exists:cash_sessions,uuid'],
            'type' => ['required', 'string', 'in:opening,closing,partial,audit'],
            'reason' => ['nullable', 'string', 'max:200', 'required_if:type,partial,audit'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'denominations' => ['required', 'array'],
            'denominations.bills' => ['required', 'array'],
            'denominations.coins' => ['required', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'session_uuid.required' => 'El UUID de la sesión es requerido.',
            'type.required' => 'El tipo de arqueo es requerido.',
            'type.in' => 'El tipo debe ser: opening, closing, partial o audit.',
            'denominations.required' => 'Las denominaciones son requeridas.',
            'reason.required_if' => 'La razón es requerida para arqueos parciales o de auditoría.',
        ];
    }
}
