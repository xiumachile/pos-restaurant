<?php

namespace Modules\Orders\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Orders\Domain\Entities\Order;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // HALLAZGO 11: Autorización centralizada en OrderPolicy
        $order = Order::where('uuid', $this->route('uuid') ?? $this->route('order'))->first();
        
        if (!$order) {
            return true; // Dejar que el controlador maneje 404
        }
        
        // Validar si el user puede hacer CUALQUIER acción sobre el order
        // El service layer validará qué transiciones específicas puede hacer
        $allowedActions = [
            'update', 'confirm', 'cancel', 'pay', 'close',
            'prepare', 'ready', 'readyForPickup', 'pickup',
            'dispatch', 'deliver', 'serve'
        ];
        
        foreach ($allowedActions as $action) {
            if (Gate::allows($action, $order)) {
                return true;
            }
        }
        
        return false;
    }

    public function rules(): array
    {
        return [
            'version' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['draft', 'confirmed', 'preparing', 'ready', 'served', 'paid', 'closed', 'cancelled'])],
            'table_uuid' => ['nullable', 'uuid', 'exists:restaurant_tables,uuid'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'version.required' => 'El campo version es requerido para control de concurrencia.',
            'version.integer' => 'El campo version debe ser un entero.',
            'version.min' => 'El campo version debe ser al menos 1.',
            'status.in' => 'El estado del pedido es inválido.',
            'table_uuid.exists' => 'La mesa especificada no existe.',
            'table_uuid.uuid' => 'El UUID de la mesa es inválido.',
        ];
    }
}
