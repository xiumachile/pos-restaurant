<?php

namespace Modules\Recipes\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['manager', 'admin']);
    }

    public function rules(): array
    {
        return [
            'product_uuid' => ['required', 'uuid', 'exists:products,uuid'],
            'description' => ['nullable', 'string', 'max:1000'],
            'yield_servings' => ['nullable', 'integer', 'min:1', 'max:100'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.raw_ingredient_id' => ['required', 'integer', 'exists:raw_ingredients,id'],
            'ingredients.*.quantity_base_unit' => ['required', 'numeric', 'min:0.0001'],
            'ingredients.*.waste_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_uuid.required' => 'El UUID del producto es requerido.',
            'product_uuid.exists' => 'El producto no existe.',
            'ingredients.required' => 'Se requiere al menos un ingrediente.',
            'ingredients.*.raw_ingredient_id.exists' => 'El insumo especificado no existe.',
            'ingredients.*.quantity_base_unit.min' => 'La cantidad debe ser mayor a 0.',
            'ingredients.*.waste_percentage.max' => 'La merma no puede exceder el 100%.',
        ];
    }
}
