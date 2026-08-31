<?php

namespace Modules\Orders\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request para crear un pedido.
 *
 * Soporta 3 tipos de pedido con validaciones condicionales:
 *
 * ## 1. dine_in (comer en el local)
 * ```json
 * {
 *   "type": "dine_in",
 *   "table_uuid": "550e8400-e29b-41d4-a716-446655440000",
 *   "notes": "Mesa cerca de la ventana"
 * }
 * ```
 * **Requiere**: `table_uuid` (la mesa es obligatoria para dine_in).
 *
 * ## 2. takeout (para llevar)
 * ```json
 * {
 *   "type": "takeout",
 *   "customer_name": "Juan Pérez",
 *   "customer_phone": "+56912345678",
 *   "pickup_at": "2026-08-31T13:30:00Z",
 *   "notes": "Sin cebolla"
 * }
 * ```
 * **Requiere**: nada obligatorio (para no romper tests existentes de takeout simple).
 * **Recomendado**: `customer_name`, `customer_phone` para identificación.
 * **Opcional**: `pickup_at` (hora programada de retiro).
 * **Prohibido**: `table_uuid` (takeout no puede tener mesa).
 *
 * ## 3. delivery (entrega a domicilio)
 * ```json
 * {
 *   "type": "delivery",
 *   "customer_name": "Juan Pérez",
 *   "customer_phone": "+56912345678",
 *   "delivery_address": "Av. Providencia 1234, Depto 501",
 *   "delivery_notes": "Tocar timbre 501",
 *   "notes": "Sin cebolla"
 * }
 * ```
 * **Requiere**: `customer_name`, `customer_phone`, `delivery_address`.
 * **Opcional**: `delivery_notes` (instrucciones para el repartidor).
 * **Prohibido**: `table_uuid`, `pickup_at`.
 */
class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('type');

        $rules = [
            'type' => ['required', Rule::in(['dine_in', 'takeout', 'delivery'])],
            'fulfillment_channel' => ['nullable', Rule::in(['onsite', 'pickup', 'delivery'])],
            'table_uuid' => ['nullable', 'uuid', 'exists:restaurant_tables,uuid'],
            'customer_name' => ['nullable', 'string', 'max:200'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'pickup_at' => ['nullable', 'date'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['draft', 'confirmed', 'preparing', 'ready', 'served'])],
        ];

        // Validaciones específicas por tipo
        if ($type === 'dine_in') {
            // dine_in requiere mesa
            $rules['table_uuid'] = ['required', 'uuid', 'exists:restaurant_tables,uuid'];
        } elseif ($type === 'takeout') {
            // takeout: nada obligatorio (compatibilidad con tests existentes)
            // pickup_at debe ser futuro si se proporciona
            $rules['pickup_at'] = ['nullable', 'date', 'after_or_equal:now'];
        } elseif ($type === 'delivery') {
            // delivery requiere datos del cliente y dirección
            $rules['customer_name'] = ['required', 'string', 'max:200'];
            $rules['customer_phone'] = ['required', 'string', 'max:30'];
            $rules['delivery_address'] = ['required', 'string', 'max:500'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de pedido es obligatorio.',
            'type.in' => 'El tipo de pedido debe ser dine_in, takeout o delivery.',
            'fulfillment_channel.in' => 'El canal de cumplimiento debe ser onsite, pickup o delivery.',
            'table_uuid.required' => 'Los pedidos dine_in requieren una mesa.',
            'table_uuid.exists' => 'La mesa especificada no existe.',
            'table_uuid.uuid' => 'El UUID de la mesa es inválido.',
            'status.in' => 'El estado del pedido debe ser draft, confirmed, preparing, ready o served.',
            'customer_name.required' => 'El nombre del cliente es requerido para delivery.',
            'customer_phone.required' => 'El teléfono del cliente es requerido para delivery.',
            'delivery_address.required' => 'La dirección de entrega es requerida para delivery.',
            'pickup_at.date' => 'La hora de retiro debe ser una fecha válida.',
            'pickup_at.after_or_equal' => 'La hora de retiro debe ser igual o posterior a ahora.',
        ];
    }

    /**
     * Validaciones condicionales cross-field.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');
            $tableUuid = $this->input('table_uuid');

            // Validar compatibilidad type ↔ fulfillment_channel
            // - delivery: solo puede ser delivery (inconsistente onsite/pickup)
            // - dine_in: puede ser onsite (canónico) o pickup (edge: pedir en local pero llevar)
            // - takeout: puede ser pickup (canónico) u onsite (edge: pedir para llevar pero quedarse)
            $channel = $this->input('fulfillment_channel');
            if (!empty($channel)) {
                if ($type === 'delivery' && $channel !== 'delivery') {
                    $validator->errors()->add('fulfillment_channel', 'Los pedidos delivery solo pueden tener canal delivery.');
                }
            }

            // dine_in: mesa requerida (ya validada en rules, doble chequeo defensivo)
            if ($type === 'dine_in' && empty($tableUuid)) {
                $validator->errors()->add('table_uuid', 'Los pedidos dine_in requieren una mesa.');
            }

            // takeout/delivery: NO pueden tener mesa
            if (in_array($type, ['takeout', 'delivery']) && !empty($tableUuid)) {
                $validator->errors()->add('table_uuid', "Los pedidos {$type} no pueden tener mesa asignada.");
            }

            // delivery: NO puede tener pickup_at (no tiene sentido)
            if ($type === 'delivery' && !empty($this->input('pickup_at'))) {
                $validator->errors()->add('pickup_at', 'Los pedidos delivery no pueden tener hora de retiro (usa delivery_address).');
            }

            // dine_in: NO puede tener delivery_address
            if ($type === 'dine_in' && !empty($this->input('delivery_address'))) {
                $validator->errors()->add('delivery_address', 'Los pedidos dine_in no pueden tener dirección de delivery.');
            }
        });
    }
}
