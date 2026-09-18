<?php

namespace Modules\Payments\Domain\Services;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\DB;
use Modules\Orders\Domain\Entities\Order;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Exceptions\PaymentException;
use Modules\Payments\Domain\ValueObjects\PaymentStatus;
use Modules\Accounting\Domain\Entities\Account;
use Modules\Payments\Domain\Services\PaymentLedgerService;

class PaymentService
{
    public function __construct(
        private PaymentLedgerService $paymentLedgerService
    ) {}

    public function registerPayment(
        Order $order,
        PaymentMethod $paymentMethod,
        int $amount,  // ADR-011: integer CLP
        string $idempotencyKey,
        ?Bill $bill = null,
        ?CashSession $cashSession = null,
        int $userId = 0,
        int $tipAmount = 0,  // ADR-011: integer CLP
        ?string $referenceCode = null,
        ?string $notes = null
    ): Payment {
        Account::seedDefaultsFor($order->company_id, $order->branch_id);

        return DB::transaction(function () use (
            $order, $paymentMethod, $amount, $idempotencyKey,
            $bill, $cashSession, $userId, $tipAmount, $referenceCode, $notes
        ) {
            $order = Order::lockForUpdate()->find($order->id);


            Log::info('Payment registration started', [
                'order_id' => $order->id,
                'payment_method' => $paymentMethod->code,
                'amount' => $amount,
                'tip_amount' => $tipAmount ?? 0,
                'idempotency_key' => $idempotencyKey,
            ]);

            // ADR-002: Scope por tenant para prevenir cross-tenant leakage
            $existing = Payment::where('company_id', $order->company_id)
                ->where('branch_id', $order->branch_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return $existing;
            }

            if (!$this->isOrderPayable($order)) {
                throw PaymentException::orderNotPayable();
            }

            if (!$paymentMethod->is_active) {
                throw PaymentException::invalidPaymentMethod();
            }

            if (!$paymentMethod->acceptsAmount($amount)) {
                throw PaymentException::invalidPaymentMethod();
            }

            $available = $this->getAvailableAmount($order, $bill);
            if ($amount > $available) {  // ADR-011: integer comparison
                throw PaymentException::insufficientAmount($amount, $available);
            }

            $totalAmount = Payment::calculateTotal($amount, $tipAmount);

            $payment = Payment::create([
                'company_id' => $order->company_id,
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'bill_id' => $bill?->id,
                'cash_session_id' => $cashSession?->id,
                'payment_method_id' => $paymentMethod->id,
                'user_id' => $userId,
                'payment_number' => Payment::generatePaymentNumber($order->branch->code),
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

            try {
                $this->paymentLedgerService->recordPayment($payment);
            } catch (\Exception $e) {
                throw PaymentException::ledgerRecordingFailed($e->getMessage());
            }

            if ($bill) {
                $bill->registerPaymentAmount($amount);
            }

            $this->updateOrderPaymentStatus($order);

            return $payment;
        });
    }

    private function isOrderPayable(Order $order): bool
    {
        return $order->status->isChargeable();
    }

    /**
     * Calcula monto disponible para pago.
     * 
     * Soporta ambos modelos:
     * - BRUTO (ADR-011): amount_due = grand_total + tip_amount
     * - Legacy: total = grand_total, tip_amount separado
     * 
     * Disponible = total_a_pagar - (pagos_venta + pagos_propina)
     */
    private function getAvailableAmount(Order $order, ?Bill $bill): int  // ADR-011: integer CLP
    {
        if ($bill) {
            return (int) $bill->remaining_amount;
        }

        // Calcular el total a pagar (venta + propina)
        $amountDue = (int) $order->amount_due;
        
        // Si amount_due no está calculado (0), usar modelo legacy
        // Fallback: total (legacy grand_total) + tip_amount
        if ($amountDue < 1) {  // ADR-011: integer comparison
            $amountDue = (int) $order->total + (int) ($order->tip_amount ?? 0);
        }

        // Sumar pagos completados (venta + propina por separado)
        $paidAmount = (int) Payment::where('order_id', $order->id)
            ->completed()
            ->sum('amount');

        $paidTips = (int) Payment::where('order_id', $order->id)
            ->completed()
            ->sum('tip_amount');

        return $amountDue - ($paidAmount + $paidTips);
    }

    private function updateOrderPaymentStatus(Order $order): void
    {
        $paidAmount = (int) Payment::where('order_id', $order->id)
            ->completed()
            ->sum('amount');

        if ($paidAmount >= (int) $order->total && $order->status->isChargeable()) {  // ADR-011: integer comparison
            $order->paid_at = now();
            $order->status = \Modules\Orders\Domain\ValueObjects\OrderStatus::PAID;
            $order->cashier_id = $order->cashier_id ?: auth()->id();
            $order->save();

            if (class_exists(\Modules\Orders\Domain\Events\OrderPaid::class)) {
                event(new \Modules\Orders\Domain\Events\OrderPaid($order));
            }
        }
    }
}
