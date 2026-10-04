<?php

namespace Modules\Tables\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para PATCH /api/v1/dining-zones/{uuid}
 *
 * Solo permite actualizar campos visibles (color, nombre, orden).
 * El code no se puede cambiar (es identificador).
 */
class UpdateDiningZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_translations' => ['sometimes', 'array'],
            'name_translations.es' => ['required_with:name_translations', 'string', 'max:100'],
            'name_translations.zh' => ['required_with:name_translations', 'string', 'max:100'],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'color.regex' => 'El color debe ser un código hexadecimal válido (#RRGGBB).',
        ];
    }
}
