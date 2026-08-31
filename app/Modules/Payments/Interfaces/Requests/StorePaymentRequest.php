<?php

namespace Modules\Payments\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request para registrar un pago de un pedido o sub-cuenta.
 * 
 * Este endpoint registra un pago parcial o total de un pedido. Soporta múltiples
 * métodos de pago (efectivo, tarjeta, transferencia) y permite pagos parciales
 * cuando un pedido se divide en varias sub-cuentas (bills).
 * 
 * ## Idempotencia (requerido):
 * 
 * Este endpoint requiere el header `Idempotency-Key` o el campo `idempotency_key`
 * para prevenir pagos duplicados en caso de retry de red. Si se envía el mismo
 * `idempotency_key` dos veces, el segundo request retorna el pago ya creado.
 * 
 * ```json
 * {
 *   "order_uuid": "550e8400-e29b-41d4-a716-446655440000",
 *   "payment_method_uuid": "660e8400-e29b-41d4-a716-446655440001",
 *   "amount": 15000,
 *   "idempotency_key": "unique-uuid-for-this-payment-attempt"
 * }
 * ```
 * 
 * ## Pago de pedido completo:
 * 
 * ```json
 * {
 *   "order_uuid": "550e8400-e29b-41d4-a716-446655440000",
 *   "payment_method_uuid": "660e8400-e29b-41d4-a716-446655440001",
 *   "amount": 45000,
 *   "tip_amount": 5000,
 *   "idempotency_key": "uuid-1"
 * }
 * ```
 * 
 * ## Pago de sub-cuenta (bill):
 * 
 * Cuando un pedido se dividió en varias sub-cuentas, se especifica `bill_uuid`:
 * 
 * ```json
 * {
 *   "order_uuid": "550e8400-e29b-41d4-a716-446655440000",
 *   "bill_uuid": "770e8400-e29b-41d4-a716-446655440002",
 *   "payment_method_uuid": "660e8400-e29b-41d4-a716-446655440001",
 *   "amount": 15000,
 *   "idempotency_key": "uuid-2"
 * }
 * ```
 * 
 * ## Pago con tarjeta (requiere reference_code):
 * 
 * Los métodos de pago configurados con `requires_reference: true` (típicamente tarjetas)
 * requieren un código de referencia (número de autorización del banco):
 * 
 * ```json
 * {
 *   "order_uuid": "550e8400-e29b-41d4-a716-446655440000",
 *   "payment_method_uuid": "880e8400-e29b-41d4-a716-446655440003",
 *   "amount": 45000,
 *   "reference_code": "AUTH123456",
 *   "idempotency_key": "uuid-3"
 * }
 * ```
 * 
 * ## Validaciones:
 * 
 * - El pedido debe estar en estado `served` o `paid` (no se puede pagar un pedido cancelado)
 * - El monto no puede exceder el total pendiente del pedido o sub-cuenta
 * - El método de pago debe estar activo y disponible para la sucursal
 * - Si la empresa tiene `requires_cashier_session` habilitado, debe existir una sesión de caja abierta
 * 
 * @see \Modules\Payments\Interfaces\Controllers\PaymentController::store()
 */
class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['cashier', 'admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            'order_uuid' => ['required', 'uuid', 'exists:orders,uuid'],
            'payment_method_uuid' => ['required', 'uuid', 'exists:payment_methods,uuid'],
            'bill_uuid' => ['nullable', 'uuid', 'exists:bills,uuid'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
            'reference_code' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_uuid.required' => 'El UUID del pedido es requerido.',
            'order_uuid.exists' => 'El pedido especificado no existe.',
            'payment_method_uuid.required' => 'El método de pago es requerido.',
            'payment_method_uuid.exists' => 'El método de pago no existe.',
            'bill_uuid.exists' => 'El bill especificado no existe.',
            'amount.required' => 'El monto es requerido.',
            'amount.min' => 'El monto debe ser mayor a 0.',
            'tip_amount.min' => 'La propina no puede ser negativa.',
            'idempotency_key.required' => 'El Idempotency-Key es requerido.',
            'idempotency_key.uuid' => 'El Idempotency-Key debe ser un UUID válido.',
            'reference_code.required_if' => 'El código de referencia es requerido para pagos con tarjeta.',
        ];
    }

    /**
     * F2.3: Validación condicional para reference_code en pagos con tarjeta.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $paymentMethodUuid = $this->input('payment_method_uuid');
            $referenceCode = $this->input('reference_code');

            if (!$paymentMethodUuid) {
                return;
            }

            // Buscar el método de pago
            $paymentMethod = \Modules\Payments\Domain\Entities\PaymentMethod::where('uuid', $paymentMethodUuid)->first();

            if ($paymentMethod && $paymentMethod->requires_reference && empty($referenceCode)) {
                $validator->errors()->add('reference_code', 'El código de referencia es requerido para este método de pago.');
            }
        });
    }
}
