<?php

namespace Modules\Companies\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo super_admin puede crear empresas
        return $this->user() && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'tax_id' => 'required|string|max:30|unique:companies,tax_id',
            'legal_name' => 'required|string|max:255',
            'trade_name' => 'required|string|max:255',
            'default_locale' => 'sometimes|string|max:10',
            'fallback_locale' => 'sometimes|string|max:10',
            'is_active' => 'sometimes|boolean',
            'settings' => 'sometimes|array',
            'enable_all_capabilities' => 'sometimes|boolean',
        ];
    }
}
