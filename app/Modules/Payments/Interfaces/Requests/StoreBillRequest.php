<?php

declare(strict_types=1);

namespace Modules\Payments\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreBillRequest: Valida payload para sincronización de bills desde frontend offline.
 * 
 * ADR-020: Bills sincronizables en flujo offline
 */
class StoreBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['cashier', 'admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            'order_uuid' => ['required', 'uuid', 'exists:orders,uuid'],
            'bill_number' => ['required', 'string', 'max:20'],
            'type' => ['required', 'string', Rule::in(['single', 'equal_split', 'by_items', 'custom_amount'])],
            'subtotal' => ['required', 'integer', 'min:0'],
            'tax_amount' => ['required', 'integer', 'min:0'],
            'discount_amount' => ['required', 'integer', 'min:0'],
            'tip_amount' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:0'],
            'paid_amount' => ['required', 'integer', 'min:0'],
            'remaining_amount' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', Rule::in(['open', 'partial', 'paid', 'cancelled'])],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_uuid.required' => 'El UUID del pedido es requerido.',
            'order_uuid.exists' => 'El pedido especificado no existe.',
            'bill_number.required' => 'El número de cuenta es requerido.',
            'type.in' => 'El tipo debe ser: single, equal_split, by_items o custom_amount.',
            'subtotal.integer' => 'El subtotal debe ser entero (ADR-018).',
            'total.integer' => 'El total debe ser entero (ADR-018).',
            'idempotency_key.required' => 'La llave de idempotencia es requerida.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que order pertenece al tenant (company_id del usuario)
            $orderUuid = $this->input('order_uuid');
            $companyId = $this->user()->company_id;

            $order = \Modules\Orders\Domain\Entities\Order::where('uuid', $orderUuid)
                ->where('company_id', $companyId)
                ->first();

            if (!$order) {
                $validator->errors()->add('order_uuid', 'El pedido no pertenece a esta empresa.');
                return;
            }

            // Validar invariante: paid_amount + remaining_amount = total
            $paidAmount = (int) $this->input('paid_amount');
            $remainingAmount = (int) $this->input('remaining_amount');
            $total = (int) $this->input('total');

            if ($paidAmount + $remainingAmount !== $total) {
                $validator->errors()->add(
                    'remaining_amount',
                    'Invariante violada: paid_amount + remaining_amount debe ser igual a total'
                );
            }
        });
    }
}
