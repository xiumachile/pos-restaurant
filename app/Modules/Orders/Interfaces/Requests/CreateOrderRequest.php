<?php

namespace Modules\Orders\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Orders\Domain\Entities\Order;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // HALLAZGO 11: Autorización centralizada en OrderPolicy
        // Solo waiter, cashier, manager, admin pueden crear pedidos
        // Usamos Gate::allows() directamente para evitar recursión infinita
        return Gate::allows('create', Order::class);
    }

    public function rules(): array
    {
        $type = $this->input('type', $this->route('type'));

        $rules = [
            'type' => ['required', Rule::in(['dine_in', 'takeout', 'delivery'])],
            'fulfillment_channel' => ['nullable', Rule::in(['onsite', 'pickup', 'delivery'])],
            'table_uuid' => ['nullable', 'uuid', 'exists:restaurant_tables,uuid'],
            'customer_id' => ['nullable', 'uuid', 'exists:customers,uuid'],
            'customer_name' => ['nullable', 'string', 'max:200'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'pickup_at' => ['nullable', 'date'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        // Validaciones específicas por tipo
        if ($type === 'dine_in') {
            $rules['table_uuid'] = ['required', 'uuid', 'exists:restaurant_tables,uuid'];
        } elseif ($type === 'takeout') {
            $rules['pickup_at'] = ['nullable', 'date', 'after_or_equal:now'];
        } elseif ($type === 'delivery') {
            // delivery requiere customer_id O datos completos del cliente
            $hasCustomerId = $this->filled('customer_id');

            if (!$hasCustomerId) {
                $rules['customer_name'] = ['required', 'string', 'max:200'];
                $rules['customer_phone'] = ['required', 'string', 'max:30'];
                $rules['delivery_address'] = ['required', 'string', 'max:500'];
            } else {
                $rules['customer_id'] = ['required', 'uuid', 'exists:customers,uuid'];
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'type.required' => 'validation.order.type_required',
            'type.in' => 'validation.order.type_invalid',
            'fulfillment_channel.in' => 'validation.order.channel_invalid',
            'table_uuid.required' => 'validation.order.table_required',
            'table_uuid.exists' => 'validation.order.table_not_found',
            'table_uuid.uuid' => 'validation.order.table_uuid_invalid',
            'customer_name.required' => 'validation.order.customer_name_required',
            'customer_phone.required' => 'validation.order.customer_phone_required',
            'delivery_address.required' => 'validation.order.delivery_address_required',
            'customer_id.exists' => 'validation.order.customer_not_found',
            'customer_id.required' => 'validation.order.customer_id_required',
            'pickup_at.date' => 'validation.order.pickup_date_invalid',
            'pickup_at.after_or_equal' => 'validation.order.pickup_date_past',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');
            $tableUuid = $this->input('table_uuid');
            $channel = $this->input('fulfillment_channel');

            if (!empty($channel) && $type === 'delivery' && $channel !== 'delivery') {
                $validator->errors()->add('fulfillment_channel', 'validation.order.delivery_channel_mismatch');
            }

            if ($type === 'dine_in' && empty($tableUuid)) {
                $validator->errors()->add('table_uuid', 'validation.order.table_required');
            }

            if (in_array($type, ['takeout', 'delivery']) && !empty($tableUuid)) {
                $validator->errors()->add('table_uuid', "validation.order.{$type}_no_table");
            }

            if ($type === 'delivery' && !empty($this->input('pickup_at'))) {
                $validator->errors()->add('pickup_at', 'validation.order.delivery_no_pickup');
            }

            if ($type === 'dine_in' && !empty($this->input('delivery_address'))) {
                $validator->errors()->add('delivery_address', 'validation.order.dinein_no_delivery_address');
            }
        });
    }
}
