<?php

namespace Modules\Tables\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by policies
    }

    public function rules(): array
    {
        return [
            'area_code' => ['required', 'string', 'max:50'],
            'area_name_translations' => ['required', 'array'],
            'area_name_translations.es' => ['required', 'string', 'max:100'],
            'area_name_translations.zh' => ['required', 'string', 'max:100'],
            'table_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('restaurant_tables', 'table_number')
                    ->where('branch_id', $this->user()->branch_id)
                    ->whereNull('deleted_at'),
            ],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'table_number.unique' => 'Ya existe una mesa con este número en la sucursal.',
            'area_name_translations.es.required' => 'El nombre del área en español es obligatorio.',
            'area_name_translations.zh.required' => 'El nombre del área en chino es obligatorio.',
        ];
    }
}
