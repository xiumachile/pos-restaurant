<?php

namespace Modules\Tables\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Tables\Domain\ValueObjects\TableShape;

/**
 * Validación para PUT /api/v1/floor-plan
 *
 * Recibe un array de posiciones de mesas y valida que:
 * - Todas las mesas existan y pertenezcan al branch del usuario
 * - Los shapes sean válidos (square, round, rectangle)
 * - Las rotaciones sean múltiplos de 90 (0, 90, 180, 270)
 * - Las coordenadas sean enteros no negativos
 */
class SaveFloorPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validShapes = array_column(TableShape::cases(), 'value');
        $branchId = $this->user()->branch_id;

        return [
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
        ];
    }

    public function messages(): array
    {
        return [
            'tables.required' => 'Debe enviar al menos una mesa.',
            'tables.*.uuid.exists' => 'La mesa no existe o no pertenece a esta sucursal.',
            'tables.*.rotation.in' => 'La rotación debe ser 0, 90, 180 o 270 grados.',
            'tables.*.shape.in' => 'La forma debe ser square, round o rectangle.',
        ];
    }
}
