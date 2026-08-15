<?php

namespace Modules\Catalog\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Catalog\Application\UseCases\SetComboItemSubstitutionPolicy;

/**
 * Validación para configurar política de sustitución en combos.
 */
class SetSubstitutionPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización se hace en el controller por rol
    }

    public function rules(): array
    {
        return [
            'mode' => 'required|string|in:' . implode(',', SetComboItemSubstitutionPolicy::ALLOWED_MODES),
            'allowed_category_id' => 'nullable|uuid|exists:categories,uuid',
            'branch_id' => 'nullable|uuid|exists:branches,uuid',
            'max_price_delta' => 'nullable|numeric|min:0',
            'requires_authorization' => 'boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $mode = $this->input('mode');

            // allowed_category_id es obligatorio si mode = allowed_category
            if ($mode === SetComboItemSubstitutionPolicy::MODE_ALLOWED_CATEGORY) {
                if (!$this->has('allowed_category_id') || $this->input('allowed_category_id') === null) {
                    $validator->errors()->add(
                        'allowed_category_id',
                        'El campo allowed_category_id es obligatorio cuando mode es allowed_category.'
                    );
                }
            }

            // Prohibir campos innecesarios en no_substitution
            if ($mode === SetComboItemSubstitutionPolicy::MODE_NO_SUBSTITUTION) {
                if ($this->has('allowed_category_id') && $this->input('allowed_category_id') !== null) {
                    $validator->errors()->add(
                        'allowed_category_id',
                        'El campo allowed_category_id no es permitido cuando mode es no_substitution.'
                    );
                }
                if ($this->has('max_price_delta') && $this->input('max_price_delta') !== null) {
                    $validator->errors()->add(
                        'max_price_delta',
                        'El campo max_price_delta no es permitido cuando mode es no_substitution.'
                    );
                }
                if ($this->has('requires_authorization') && $this->input('requires_authorization') === true) {
                    $validator->errors()->add(
                        'requires_authorization',
                        'El campo requires_authorization no es permitido cuando mode es no_substitution.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'mode.required' => 'El modo es obligatorio.',
            'mode.in' => 'El modo debe ser: ' . implode(', ', SetComboItemSubstitutionPolicy::ALLOWED_MODES),
            'allowed_category_id.exists' => 'La categoría especificada no existe.',
            'branch_id.exists' => 'La sucursal especificada no existe.',
            'max_price_delta.min' => 'El recargo máximo no puede ser negativo.',
        ];
    }
}
