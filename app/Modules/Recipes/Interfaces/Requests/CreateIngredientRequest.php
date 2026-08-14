<?php

namespace Modules\Recipes\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Recipes\Domain\ValueObjects\BaseUnit;
use Modules\Recipes\Domain\ValueObjects\DimensionType;

class CreateIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['manager', 'admin']);
    }

    public function rules(): array
    {
        $validDimensions = array_column(DimensionType::cases(), 'value');
        $validUnits = array_column(BaseUnit::cases(), 'value');

        return [
            'sku' => ['required', 'string', 'max:100', 'unique:raw_ingredients,sku'],
            'name_translations' => ['required', 'array'],
            'name_translations.es' => ['required', 'string', 'max:255'],
            'name_translations.zh' => ['nullable', 'string', 'max:255'],
            'dimension_type' => ['required', 'string', 'in:' . implode(',', $validDimensions)],
            'base_unit' => ['required', 'string', 'in:' . implode(',', $validUnits)],
            'minimum_stock_base' => ['nullable', 'numeric', 'min:0'],
            'initial_cost_per_base_unit' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.required' => 'El SKU del insumo es requerido.',
            'sku.unique' => 'El SKU ya existe.',
            'name_translations.required' => 'Las traducciones del nombre son requeridas.',
            'dimension_type.required' => 'La dimensión física es requerida.',
            'dimension_type.in' => 'La dimensión debe ser: mass, volume o count.',
            'base_unit.required' => 'La unidad base es requerida.',
            'base_unit.in' => 'La unidad debe ser: g, ml, un, kg, l, lb, doc o pack.',
        ];
    }
}
