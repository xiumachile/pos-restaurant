<?php

namespace Modules\Recipes\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['manager', 'admin']);
    }

    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:1000'],
            'yield_servings' => ['nullable', 'integer', 'min:1', 'max:100'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.raw_ingredient_id' => ['required', 'integer', 'exists:raw_ingredients,id'],
            'ingredients.*.quantity_base_unit' => ['required', 'numeric', 'min:0.0001'],
            'ingredients.*.waste_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
