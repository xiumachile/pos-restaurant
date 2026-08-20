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
            // 1. Verificar idempotencia
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

            // 4. Validar monto disponible
            $available = $this->getAvailableAmount($order, $bill);
            if ($amount > $available + 0.01) {
                throw PaymentException::insufficientAmount($amount, $available);
            }

            // 5. Crear el pago (reference_code es opcional)
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

            // 6. Actualizar el bill si aplica
            if ($bill) {
                $bill->registerPaymentAmount($amount);
            }

            // 7. Actualizar el pedido si está completamente pagado
            $this->updateOrderPaymentStatus($order);

            return $payment;
        });
    }

    /**
     * Verifica si un pedido puede recibir pagos.
     * Acepta cualquier estado cobrable: CONFIRMED, PREPARING, READY, SERVED
     */
    private function isOrderPayable(Order $order): bool
    {
        return $order->status->isChargeable();
    }

    /**
     * Calcula el monto disponible a pagar (total - ya pagado).
     */
    private function getAvailableAmount(Order $order, ?Bill $bill): float
    {
        if ($bill) {
            return (float) $bill->remaining_amount;
        }

        $paidAmount = (float) Payment::where('order_id', $order->id)
            ->completed()
            ->sum('amount');

        return (float) $order->total - $paidAmount;
    }

    /**
     * Actualiza el estado de pago del pedido si está completamente pagado.
     * Transiciona el order a PAID desde cualquier estado cobrable.
     */
    private function updateOrderPaymentStatus(Order $order): void
    {
        $paidAmount = (float) Payment::where('order_id', $order->id)
            ->completed()
            ->sum('amount');

        if ($paidAmount >= (float) $order->total && $order->status->isChargeable()) {
            $order->paid_at = now();
            $order->status = \Modules\Orders\Domain\ValueObjects\OrderStatus::PAID;
            $order->cashier_id = $order->cashier_id ?: auth()->id();
            $order->save();
            
            // Disparar evento OrderPaid si existe
            if (class_exists(\Modules\Orders\Domain\Events\OrderPaid::class)) {
                event(new \Modules\Orders\Domain\Events\OrderPaid($order));
            }
        }
    }
}
