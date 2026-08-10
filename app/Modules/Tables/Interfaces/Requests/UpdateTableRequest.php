<?php

namespace Modules\Tables\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tableUuid = $this->route('table');

        return [
            'area_code' => ['sometimes', 'string', 'max:50'],
            'area_name_translations' => ['sometimes', 'array'],
            'area_name_translations.es' => ['sometimes', 'string', 'max:100'],
            'area_name_translations.zh' => ['sometimes', 'string', 'max:100'],
            'table_number' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('restaurant_tables', 'table_number')
                    ->where('branch_id', $this->user()->branch_id)
                    ->whereNull('deleted_at')
                    ->ignore($tableUuid, 'uuid'),
            ],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
