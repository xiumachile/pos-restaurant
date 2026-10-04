<?php

namespace Modules\Tables\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación para POST /api/v1/dining-zones
 */
class StoreDiningZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $branchId = $this->user()->branch_id;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('dining_zones', 'code')
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at'),
            ],
            'name_translations' => ['required', 'array'],
            'name_translations.es' => ['required', 'string', 'max:100'],
            'name_translations.zh' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'floor_level' => ['nullable', 'integer', 'min:0', 'max:10'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Ya existe una zona con este código en la sucursal.',
            'code.regex' => 'El código solo puede contener letras mayúsculas, números y guiones bajos.',
            'color.regex' => 'El color debe ser un código hexadecimal válido (#RRGGBB).',
        ];
    }
}
