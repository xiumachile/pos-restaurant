<?php

declare(strict_types=1);

namespace Modules\Payments\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreBillRequest: Valida payload para sincronización de bills desde frontend offline.
 * 
 * HALLAZGO CA-02: El cliente NO envía montos calculados (subtotal, total, etc.) como fuente de verdad.
 * En su lugar, envía la INTENCIÓN de división (qué items, qué tipo), y el servidor recalcula todo.
 * Los campos 'client_*' son solo para diagnóstico y logging de discrepancias.
 */
class StoreBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['cashier', 'admin', 'manager', 'waiter']);
    }

    public function rules(): array
    {
        return [
            'order_uuid' => ['required', 'uuid'],
            'idempotency_key' => ['required', 'uuid'],
            'type' => ['required', 'string', Rule::in(['single', 'by_items', 'custom_amount', 'equal_split'])],
            
            // Intención de división
            'item_uuids' => ['nullable', 'array', 'required_if:type,by_items'],
            'item_uuids.*' => ['string', 'uuid'],
            
            'parts' => ['nullable', 'integer', 'min:2', 'required_if:type,equal_split'],
            
            'client_amount' => ['nullable', 'integer', 'min:0', 'required_if:type,custom_amount'],
            
            // Campos de diagnóstico (opcionales, el servidor los ignora para el cálculo)
            'client_subtotal' => ['nullable', 'integer'],
            'client_total' => ['nullable', 'integer'],
            'client_paid_amount' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_uuid.required' => 'El UUID del pedido es requerido.',
            'idempotency_key.required' => 'La llave de idempotencia es requerida.',
            'type.in' => 'El tipo debe ser: single, by_items, custom_amount o equal_split.',
            'item_uuids.required_if' => 'Los UUIDs de los items son requeridos para división por items.',
            'parts.required_if' => 'El número de partes es requerido para división igual.',
            'client_amount.required_if' => 'El monto es requerido para división por monto personalizado.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $orderUuid = $this->input('order_uuid');
            $companyId = $this->user()->company_id;

            $order = \Modules\Orders\Domain\Entities\Order::where('uuid', $orderUuid)
                ->where('company_id', $companyId)
                ->with('items')
                ->first();

            if (!$order) {
                $validator->errors()->add('order_uuid', 'El pedido no existe o no pertenece a esta empresa.');
                return;
            }

            // Validar que los item_uuids proporcionados realmente pertenezcan a este order
            if ($this->input('type') === 'by_items' && $this->has('item_uuids')) {
                $orderItemUuids = $order->items->pluck('uuid')->toArray();
                $invalidItems = array_diff($this->input('item_uuids'), $orderItemUuids);
                
                if (!empty($invalidItems)) {
                    $validator->errors()->add(
                        'item_uuids',
                        'Uno o más items no pertenecen a este pedido: ' . implode(', ', $invalidItems)
                    );
                }
            }
        });
    }
}
