<?php

namespace Modules\Kitchen\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignCookRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo kitchen, admin y manager pueden asignar cocineros
        $role = $this->user()->role;
        return in_array($role, ['kitchen', 'admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            'cook_uuid' => ['required', 'uuid', 'exists:users,uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'cook_uuid.required' => 'El UUID del cocinero es requerido.',
            'cook_uuid.uuid' => 'El UUID del cocinero no es válido.',
            'cook_uuid.exists' => 'El cocinero especificado no existe.',
        ];
    }
}
