<?php

namespace Modules\Payments\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Orders\Domain\Entities\Order;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Exceptions\PaymentException;
use Modules\Payments\Domain\ValueObjects\PaymentStatus;

/**
 * Servicio de dominio para registro de pagos.
 * Implementa Idempotencia según Arquitectura v1.1 Sección 12.
 */
class PaymentService
{
    /**
     * Registra un pago para un pedido o bill específico.
     * Garantiza idempotencia mediante Idempotency-Key.
     */
    public function registerPayment(
        Order $order,
        PaymentMethod $paymentMethod,
        float $amount,
        string $idempotencyKey,
        ?Bill $bill = null,
        ?CashSession $cashSession = null,
        int $userId = 0,
        float $tipAmount = 0,
        ?string $referenceCode = null,
        ?string $notes = null
    ): Payment {
        return DB::transaction(function () use (
            $order, $paymentMethod, $amount, $idempotencyKey,
            $bill, $cashSession, $userId, $tipAmount, $referenceCode, $notes
        ) {
            // 1. Verificar idempotencia (retornar pago existente si ya fue procesado)
            $existing = Payment::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            // 2. Validar que el pedido sea pagable
            if (!$this->isOrderPayable($order)) {
                throw PaymentException::orderNotPayable();
            }

            // 3. Validar método de pago
            if (!$paymentMethod->is_active) {
                throw PaymentException::invalidPaymentMethod();
            }

            if (!$paymentMethod->acceptsAmount($amount)) {
                throw PaymentException::invalidPaymentMethod();
            }

            // 4. Validar que requiera referencia si aplica
            if ($paymentMethod->requires_reference && empty($referenceCode)) {
                throw PaymentException::invalidPaymentMethod();
            }

            // 5. Calcular el monto disponible a pagar
            $available = $this->getAvailableAmount($order, $bill);
            if ($amount > $available + 0.01) {
                throw PaymentException::insufficientAmount($amount, $available);
            }

            // 6. Crear el pago
            $totalAmount = Payment::calculateTotal($amount, $tipAmount);

            $payment = Payment::create([
                'company_id' => $order->company_id,
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'bill_id' => $bill?->id,
                'cash_session_id' => $cashSession?->id,
                'payment_method_id' => $paymentMethod->id,
                'user_id' => $userId,
                'payment_number' => Payment::generatePaymentNumber($order->order_number),
                'method_code' => $paymentMethod->code,
                'amount' => $amount,
                'tip_amount' => $tipAmount,
                'total_amount' => $totalAmount,
                'reference_code' => $referenceCode,
                'status' => PaymentStatus::COMPLETED,
                'idempotency_key' => $idempotencyKey,
                'notes' => $notes,
                'paid_at' => now(),
            ]);

            // 7. Actualizar el bill si aplica
            if ($bill) {
                $bill->registerPaymentAmount($amount);
            }

            // 8. Actualizar el pedido si está completamente pagado
            $this->updateOrderPaymentStatus($order);

            return $payment;
        });
    }

    /**
     * Verifica si el pedido está en estado pagable.
     */
    public function isOrderPayable(Order $order): bool
    {
        $payableStatuses = ['served', 'closed'];
        return in_array($order->status->value, $payableStatuses);
    }

    /**
     * Obtiene el monto disponible para pagar.
     */
    public function getAvailableAmount(Order $order, ?Bill $bill = null): float
    {
        if ($bill) {
            return (float) $bill->remaining_amount;
        }

        // Calcular el pagado hasta ahora a nivel de pedido
        $paid = (float) Payment::where('order_id', $order->id)
            ->whereNull('bill_id')
            ->completed()
            ->sum('amount');

        return max(0, (float) $order->total - $paid);
    }

    /**
     * Actualiza el estado de pago del pedido.
     */
    private function updateOrderPaymentStatus(Order $order): void
    {
        $paidAmount = (float) Payment::where('order_id', $order->id)
            ->completed()
            ->sum('amount');

        if ($paidAmount >= (float) $order->total) {
            $order->paid_at = now();
            $order->save();
        }
    }
}
