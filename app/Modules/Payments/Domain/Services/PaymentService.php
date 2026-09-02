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
        float $amount,
        string $idempotencyKey,
        ?Bill $bill = null,
        ?CashSession $cashSession = null,
        int $userId = 0,
        float $tipAmount = 0,
        ?string $referenceCode = null,
        ?string $notes = null
    ): Payment {
        Account::seedDefaultsFor($order->company_id, $order->branch_id);

        return DB::transaction(function () use (
            $order, $paymentMethod, $amount, $idempotencyKey,
            $bill, $cashSession, $userId, $tipAmount, $referenceCode, $notes
        ) {
            $order = Order::lockForUpdate()->find($order->id);

            $existing = Payment::where('idempotency_key', $idempotencyKey)->first();
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
            if ($amount > $available + 0.01) {
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

            if (class_exists(\Modules\Orders\Domain\Events\OrderPaid::class)) {
                event(new \Modules\Orders\Domain\Events\OrderPaid($order));
            }
        }
    }
}
