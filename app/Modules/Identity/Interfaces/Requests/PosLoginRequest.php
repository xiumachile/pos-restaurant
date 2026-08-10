<?php

namespace Modules\Identity\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
