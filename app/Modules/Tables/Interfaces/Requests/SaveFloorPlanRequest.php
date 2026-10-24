<?php

namespace Modules\Tables\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Tables\Domain\ValueObjects\TableShape;

class SaveFloorPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validShapes = array_column(TableShape::cases(), 'value');
        $validObjectTypes = ['wall', 'pillar', 'plant', 'counter', 'decoration', 'sofa', 'station'];
        $branchId = $this->user()->branch_id;

        return [
            // Mesas existentes
            'tables' => ['required', 'array', 'min:1'],
            'tables.*.uuid' => [
                'required',
                'uuid',
                Rule::exists('restaurant_tables', 'uuid')
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at'),
            ],
            'tables.*.position_x' => ['required', 'integer', 'min:0', 'max:10000'],
            'tables.*.position_y' => ['required', 'integer', 'min:0', 'max:10000'],
            'tables.*.rotation' => ['required', 'integer', Rule::in([0, 90, 180, 270])],
            'tables.*.shape' => ['required', 'string', Rule::in($validShapes)],
            'tables.*.width' => ['nullable', 'integer', 'min:10', 'max:500'],
            'tables.*.height' => ['nullable', 'integer', 'min:10', 'max:500'],
            
            // NUEVO: Objetos decorativos
            'decorations' => ['nullable', 'array'],
            'decorations.*.uuid' => ['required', 'uuid'],
            'decorations.*.type' => ['required', 'string', Rule::in($validObjectTypes)],
            'decorations.*.position_x' => ['required', 'integer', 'min:0', 'max:10000'],
            'decorations.*.position_y' => ['required', 'integer', 'min:0', 'max:10000'],
            'decorations.*.rotation' => ['required', 'integer', 'min:0', 'max:360'],
            'decorations.*.width' => ['nullable', 'integer', 'min:10', 'max:500'],
            'decorations.*.height' => ['nullable', 'integer', 'min:10', 'max:500'],
            'decorations.*.properties' => ['nullable', 'array'],
            // ... dentro de rules()
            'canvas' => ['nullable', 'array'],
            'canvas.width' => ['nullable', 'integer', 'min:500', 'max:5000'],
            'canvas.height' => ['nullable', 'integer', 'min:500', 'max:5000'],
            'canvas.grid_size' => ['nullable', 'integer', 'in:20,40,80'],
        ];
    }

    public function messages(): array
    {
        return [
            'tables.required' => 'Debe enviar al menos una mesa.',
            'tables.*.uuid.exists' => 'La mesa no existe o no pertenece a esta sucursal.',
            'tables.*.rotation.in' => 'La rotación debe ser 0, 90, 180 o 270 grados.',
            'tables.*.shape.in' => 'La forma debe ser square, round o rectangle.',
            'decorations.*.type.in' => 'Tipo de objeto inválido.',
        ];
    }
}
