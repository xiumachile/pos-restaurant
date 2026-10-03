<?php

namespace Modules\Recipes\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Recipes\Domain\ValueObjects\MovementType;

/**
 * Validación para registrar un movimiento de insumo.
 */
class RecordMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validTypes = collect(MovementType::cases())->pluck('value')->implode(',');

        return [
            'type' => ['required', 'string', Rule::in(array_column(MovementType::cases(), 'value'))],
            'quantity_base' => ['required', 'numeric', 'not_in:0'],
            'reference_type' => ['nullable', 'string', 'max:50'],
            'reference_id' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de movimiento es obligatorio.',
            'type.in' => 'Tipo de movimiento inválido.',
            'quantity_base.required' => 'La cantidad es obligatoria.',
            'quantity_base.numeric' => 'La cantidad debe ser numérica.',
            'quantity_base.not_in' => 'La cantidad no puede ser zero.',
        ];
    }
}
