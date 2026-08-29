<?php

namespace Modules\Companies\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // admin de la empresa puede actualizar
        return $this->user() && in_array($this->user()->role, ['admin', 'super_admin']);
    }

    public function rules(): array
    {
        return [
            'tax_id' => 'sometimes|string|max:30|unique:companies,tax_id,' . $this->route('company')?->id,
            'legal_name' => 'sometimes|string|max:255',
            'trade_name' => 'sometimes|string|max:255',
            'default_locale' => 'sometimes|string|max:10',
            'fallback_locale' => 'sometimes|string|max:10',
            'is_active' => 'sometimes|boolean',
            'settings' => 'sometimes|array',
        ];
    }
}
