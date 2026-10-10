<?php

namespace Modules\Payments\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        int $amount,
        string $idempotencyKey,
        ?Bill $bill = null,
        ?CashSession $cashSession = null,
        int $userId = 0,
        int $tipAmount = 0,
        ?string $referenceCode = null,
        ?string $notes = null
    ): Payment {
        Account::seedDefaultsFor($order->company_id, $order->branch_id);

        $this->validateTenantInvariants($order, $paymentMethod, $bill, $cashSession);

        // HALLAZGO CA-01: Hash del payload para detectar reutilización maliciosa o errónea
        $payloadHash = hash('sha256', json_encode([
            'order_id' => $order->id,
            'bill_id' => $bill?->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => $amount,
            'tip_amount' => $tipAmount,
        ]));

        return DB::transaction(function () use (
            $order, $paymentMethod, $amount, $idempotencyKey, $payloadHash,
            $bill, $cashSession, $userId, $tipAmount, $referenceCode, $notes
        ) {
            // HALLAZGO CA-01: Bloquear el pedido y la cuenta (si existe) para evitar race conditions
            // en el cálculo del saldo disponible y prevención de doble cobro.
            $lockedOrder = Order::where('id', $order->id)
                ->where('company_id', $order->company_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedBill = null;
            if ($bill) {
                $lockedBill = Bill::where('id', $bill->id)
                    ->where('company_id', $bill->company_id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            Log::info('Payment registration started', [
                'order_id' => $lockedOrder->id,
                'payment_method' => $paymentMethod->code,
                'amount' => $amount,
                'tip_amount' => $tipAmount,
                'idempotency_key' => $idempotencyKey,
            ]);

            // HALLAZGO CA-01: Verificación de idempotencia con validación de payload
            $existing = Payment::where('company_id', $lockedOrder->company_id)
                ->where('branch_id', $lockedOrder->branch_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                if ($existing->payload_hash === $payloadHash) {
                    Log::info('PaymentService: Idempotency match, returning existing payment', [
                        'payment_id' => $existing->id,
                        'idempotency_key' => $idempotencyKey,
                    ]);
                    return $existing;
                } else {
                    Log::warning('PaymentService: Idempotency key reused with different payload', [
                        'payment_id' => $existing->id,
                        'idempotency_key' => $idempotencyKey,
                        'expected_hash' => $payloadHash,
                        'actual_hash' => $existing->payload_hash,
                    ]);
                    throw PaymentException::idempotencyKeyMismatch($idempotencyKey);
                }
            }

            if (!$this->isOrderPayable($lockedOrder)) {
                throw PaymentException::orderNotPayable();
            }

            if (!$paymentMethod->is_active) {
                throw PaymentException::invalidPaymentMethod();
            }

            if (!$paymentMethod->acceptsAmount($amount)) {
                throw PaymentException::invalidPaymentMethod();
            }

            $available = $this->getAvailableAmount($lockedOrder, $lockedBill);
            if ($amount > $available) {
                throw PaymentException::insufficientAmount($amount, $available);
            }

            $totalAmount = Payment::calculateTotal($amount, $tipAmount);

            try {
                $payment = Payment::create([
                    'company_id' => $lockedOrder->company_id,
                    'branch_id' => $lockedOrder->branch_id,
                    'order_id' => $lockedOrder->id,
                    'bill_id' => $lockedBill?->id,
                    'cash_session_id' => $cashSession?->id,
                    'payment_method_id' => $paymentMethod->id,
                    'user_id' => $userId,
                    'payment_number' => Payment::generatePaymentNumber($lockedOrder->branch->code, $lockedOrder->branch_id),
                    'method_code' => $paymentMethod->code,
                    'amount' => $amount,
                    'tip_amount' => $tipAmount,
                    'total_amount' => $totalAmount,
                    'reference_code' => $referenceCode,
                    'status' => PaymentStatus::COMPLETED,
                    'idempotency_key' => $idempotencyKey,
                    'payload_hash' => $payloadHash,
                    'notes' => $notes,
                    'paid_at' => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // HALLAZGO 13 / CA-01: Manejar violación de restricción única (race condition)
                if (str_contains($e->getMessage(), '23505') || str_contains($e->getMessage(), 'payments_tenant_idempotency_unique')) {
                    Log::warning('Idempotency race condition caught at DB level', [
                        'idempotency_key' => $idempotencyKey,
                        'order_id' => $lockedOrder->id,
                    ]);
                    
                    $existingPayment = Payment::where('company_id', $lockedOrder->company_id)
                        ->where('branch_id', $lockedOrder->branch_id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();
                        
                    if ($existingPayment) {
                        if ($existingPayment->payload_hash === $payloadHash) {
                            return $existingPayment;
                        }
                        throw PaymentException::idempotencyKeyMismatch($idempotencyKey);
                    }
                }
                throw $e;
            }

            try {
                $this->paymentLedgerService->recordPayment($payment);
            } catch (\Exception $e) {
                throw PaymentException::ledgerRecordingFailed($e->getMessage());
            }

            if ($lockedBill) {
                $lockedBill->registerPaymentAmount($amount);
            }

            $this->updateOrderPaymentStatus($lockedOrder);

            return $payment;
        });
    }

    private function isOrderPayable(Order $order): bool
    {
        return $order->status->isChargeable();
    }

    private function getAvailableAmount(Order $order, ?Bill $bill): int
    {
        if ($bill) {
            return (int) $bill->remaining_amount;
        }

        $amountDue = (int) $order->amount_due;
        
        if ($amountDue < 1) {
            $amountDue = (int) $order->total + (int) ($order->tip_amount ?? 0);
        }

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
        $amountDue = (int) $order->amount_due;
        
        if ($amountDue < 1) {
            $amountDue = (int) $order->total + (int) ($order->tip_amount ?? 0);
        }

        $paidAmount = (int) Payment::where('order_id', $order->id)
            ->completed()
            ->sum('amount');

        $paidTips = (int) Payment::where('order_id', $order->id)
            ->completed()
            ->sum('tip_amount');

        $totalPaid = $paidAmount + $paidTips;

        if ($totalPaid >= $amountDue && $order->status->isChargeable()) {
            $order->paid_at = now();
            $order->status = \Modules\Orders\Domain\ValueObjects\OrderStatus::PAID;
            $order->cashier_id = $order->cashier_id ?: auth()->id();
            $order->save();

            if (class_exists(\Modules\Orders\Domain\Events\OrderPaid::class)) {
                event(new \Modules\Orders\Domain\Events\OrderPaid($order));
            }
        }
    }

    private function validateTenantInvariants(
        Order $order,
        PaymentMethod $paymentMethod,
        ?Bill $bill = null,
        ?CashSession $cashSession = null
    ): void {
        if ($paymentMethod->company_id !== $order->company_id) {
            throw PaymentException::tenantMismatch('payment_method.company_id', 'order.company_id');
        }
        
        if ($paymentMethod->branch_id !== null && $paymentMethod->branch_id !== $order->branch_id) {
            throw PaymentException::tenantMismatch('payment_method.branch_id', 'order.branch_id');
        }
        
        if ($bill !== null) {
            if ($bill->company_id !== $order->company_id) {
                throw PaymentException::tenantMismatch('bill.company_id', 'order.company_id');
            }
            if ($bill->branch_id !== $order->branch_id) {
                throw PaymentException::tenantMismatch('bill.branch_id', 'order.branch_id');
            }
        }
        
        if ($cashSession !== null) {
            if ($cashSession->company_id !== $order->company_id) {
                throw PaymentException::tenantMismatch('cash_session.company_id', 'order.company_id');
            }
            if ($cashSession->branch_id !== $order->branch_id) {
                throw PaymentException::tenantMismatch('cash_session.branch_id', 'order.branch_id');
            }
            
            if (!$cashSession->canReceivePayments()) {
                throw PaymentException::cashSessionNotOpen();
            }
        }
    }
}
