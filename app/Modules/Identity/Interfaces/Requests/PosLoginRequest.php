<?php

namespace Modules\Identity\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request para login POS con PIN (legacy) o session_token (nuevo).
 *
 * Dual flow:
 * - Flujo legacy: { branch_id, pin } → O(n) en backend
 * - Flujo nuevo: { branch_id, session_token } → O(1) lookup en cache
 *
 * El session_token se obtiene llamando primero a POST /auth/pos-session.
 */
class PosLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            // PIN: requerido solo si NO hay session_token
            'pin' => [
                Rule::requiredIf(!$this->has('session_token')),
                'string',
                'regex:/^\d{4,6}$/',
            ],
            // Session token: requerido solo si NO hay PIN
            'session_token' => [
                Rule::requiredIf(!$this->has('pin')),
                'string',
                'size:32',  // 32 caracteres hex
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'pin.regex' => 'El PIN debe tener entre 4 y 6 dígitos numéricos.',
            'pin.required' => 'El PIN o session_token es requerido.',
            'session_token.required' => 'El session_token o PIN es requerido.',
            'session_token.size' => 'El session_token debe tener 32 caracteres.',
            'branch_id.exists' => 'La sucursal especificada no existe.',
        ];
    }
}
