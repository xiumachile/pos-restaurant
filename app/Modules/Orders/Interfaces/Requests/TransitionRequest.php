<?php

namespace Modules\Orders\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.string' => 'La razón debe ser texto.',
            'reason.max' => 'La razón no puede exceder 500 caracteres.',
        ];
    }
}
