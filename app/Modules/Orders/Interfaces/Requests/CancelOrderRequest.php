<?php

namespace Modules\Orders\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'La razón de cancelación es obligatoria.',
            'reason.min' => 'La razón debe tener al menos 3 caracteres.',
            'reason.max' => 'La razón no puede exceder 500 caracteres.',
        ];
    }
}
