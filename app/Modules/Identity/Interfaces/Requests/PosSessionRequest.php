<?php

namespace Modules\Identity\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para crear una sesión POS efímera.
 *
 * Este endpoint valida el PIN del usuario (una sola vez, en el setup)
 * y genera un token efímero almacenado en cache con TTL de 5 minutos.
 * El POS luego usa ese token para autenticarse en O(1) sin exponer el PIN.
 *
 * Flujo:
 * 1. POS envía { branch_id, pin } a /auth/pos-session
 * 2. Backend valida PIN contra usuarios de la sucursal
 * 3. Backend genera token efímero y lo guarda en cache (TTL 300s)
 * 4. POS recibe { session_token, expires_in }
 * 5. POS usa session_token en /auth/login/pos (O(1) lookup)
 */
class PosSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'pin' => ['required', 'string', 'regex:/^\d{4,6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'pin.regex' => 'El PIN debe tener entre 4 y 6 dígitos numéricos.',
            'branch_id.exists' => 'La sucursal especificada no existe.',
        ];
    }
}
