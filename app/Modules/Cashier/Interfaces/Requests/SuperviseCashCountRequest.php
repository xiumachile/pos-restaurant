<?php

namespace Modules\Cashier\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SuperviseCashCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['manager', 'admin']);
    }

    public function rules(): array
    {
        return [
            'explanation' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'explanation.required' => 'La justificación de la discrepancia es requerida.',
            'explanation.min' => 'La justificación debe tener al menos 10 caracteres.',
        ];
    }
}
