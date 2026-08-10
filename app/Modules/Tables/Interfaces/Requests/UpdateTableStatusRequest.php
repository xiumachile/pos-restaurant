<?php

namespace Modules\Tables\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Tables\Domain\ValueObjects\TableStatus;

class UpdateTableStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validStatuses = array_column(TableStatus::cases(), 'value');

        return [
            'status' => ['required', Rule::in($validStatuses)],
            'current_order_id' => ['required_if:status,occupied', 'nullable', 'integer', 'exists:orders,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_order_id.required_if' => 'Se requiere un pedido asociado para ocupar la mesa.',
            'current_order_id.exists' => 'El pedido especificado no existe.',
        ];
    }
}
