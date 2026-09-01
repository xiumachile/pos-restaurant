<?php

namespace Modules\Payments\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Domain\Entities\Account;
use Modules\Accounting\Domain\Services\LedgerService;
use Modules\Accounting\Domain\ValueObjects\ReferenceType;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\Refund;
use Modules\Payments\Domain\Exceptions\InvalidRefundException;
use Modules\Payments\Domain\ValueObjects\PaymentStatus;
use Modules\Payments\Domain\ValueObjects\RefundStatus;

/**
 * Servicio para procesar reembolsos de pagos.
 *
 * RESPONSABILIDADES:
 * - Validar que el refund no exceda el monto reembolsable del payment
 * - Crear asiento contable de reversa proporcional
 * - Actualizar el payment (status si es refund completo)
 * - Garantizar idempotencia
 *
 * LÓGICA DE REVERSA PROPORCIONAL:
 * Si el pago original fue $11,900 (con líneas Revenue $10,000 + Tax $1,900)
 * y el refund es $5,000, se calcula el ratio 5000/11900 = 0.4202 y se
 * revierte cada línea proporcionalmente:
 *   - Revenue: DEBIT  $10,000 × 0.4202 = $4,202
 *   - Tax:     DEBIT   $1,900 × 0.4202 =   $798
 *   - Cash:    CREDIT  $5,000 (sale efectivo)
 */
class RefundService
{
    public function __construct(
        private LedgerService $ledgerService
    ) {}

    /**
     * Crea y procesa un reembolso.
     *
     * @throws InvalidRefundException Si el amount excede el reembolsable o payment no es reembolsable
     */
    public function createRefund(
        Payment $payment,
        float $amount,
        ?string $reason = null,
        ?int $processedBy = null,
        ?string $idempotencyKey = null,
        ?string $notes = null
    ): Refund {
        // Idempotencia: si ya existe con esta key, retornar
        if ($idempotencyKey) {
            $existing = Refund::where('company_id', $payment->company_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        // Validaciones
        $this->validateRefund($payment, $amount);

        return DB::transaction(function () use ($payment, $amount, $reason, $processedBy, $idempotencyKey, $notes) {
            // Generar número de refund
            $refundNumber = $this->generateRefundNumber($payment->company_id);

            // Crear refund (estado PENDING inicialmente)
            $refund = Refund::create([
                'company_id' => $payment->company_id,
                'branch_id' => $payment->branch_id,
                'payment_id' => $payment->id,
                'refund_number' => $refundNumber,
                'amount' => $amount,
                'status' => RefundStatus::PENDING,
                'reason' => $reason,
                'processed_by' => $processedBy,
                'notes' => $notes,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Crear asiento de reversa
            $journalEntry = $this->createReversalEntry($payment, $refund);

            // Actualizar refund con journal entry y marcar como COMPLETED
            $refund->update([
                'journal_entry_id' => $journalEntry->id,
                'status' => RefundStatus::COMPLETED,
                'processed_at' => now(),
            ]);

            return $refund->load(['journalEntry.ledgerEntries.account', 'payment']);
        });
    }

    /**
     * Valida que el refund sea válido.
     *
     * @throws InvalidRefundException
     */
    private function validateRefund(Payment $payment, float $amount): void
    {
        // Payment debe estar en estado COMPLETED para poder reembolsarse
        // PENDING y FAILED no son reembolsables; REFUNDED permite refunds adicionales parciales
        if (!in_array($payment->status, [PaymentStatus::COMPLETED, PaymentStatus::REFUNDED])) {
            throw InvalidRefundException::paymentNotRefundable($payment->status->value);
        }

        // Amount debe ser positivo
        if ($amount <= 0) {
            throw InvalidRefundException::invalidAmount($amount);
        }

        // Amount no puede exceder el total del payment
        if ($amount > (float) $payment->total_amount) {
            throw InvalidRefundException::exceedsPaymentAmount($amount, (float) $payment->total_amount);
        }

        // Suma de refunds previos + este amount no puede exceder el total
        $alreadyRefunded = Refund::totalRefundedFor($payment->id);
        $maxRefundable = (float) $payment->total_amount - $alreadyRefunded;

        if ($amount > $maxRefundable) {
            throw InvalidRefundException::exceedsRefundableAmount($amount, $maxRefundable, $alreadyRefunded);
        }
    }

    /**
     * Crea asiento contable de reversa proporcional.
     *
     * Usa el journal entry original del payment para calcular proporciones.
     */
    private function createReversalEntry(Payment $payment, Refund $refund)
    {
        // Buscar el journal entry original del payment
        $originalEntries = $this->ledgerService->getEntriesByReference(
            ReferenceType::PAYMENT,
            $payment->id
        );

        // Si no hay asiento original, usar lógica simple
        if (empty($originalEntries)) {
            return $this->createSimpleReversalEntry($payment, $refund);
        }

        $originalEntry = $originalEntries[0];
        $originalTotal = (float) $payment->total_amount;
        $refundAmount = (float) $refund->amount;
        $ratio = $refundAmount / $originalTotal;

        // Construir líneas de reversa (invierte débito ↔ crédito)
        $lines = [];

        foreach ($originalEntry['ledger_entries'] as $entry) {
            $originalDebit = (float) $entry['debit_amount'];
            $originalCredit = (float) $entry['credit_amount'];

            // Invertir: si era débito, ahora es crédito; si era crédito, ahora es débito
            $reversedDebit = round($originalCredit * $ratio, 2);
            $reversedCredit = round($originalDebit * $ratio, 2);

            if ($reversedDebit > 0 || $reversedCredit > 0) {
                $lines[] = [
                    'account_id' => $entry['account_id'],
                    'debit' => $reversedDebit,
                    'credit' => $reversedCredit,
                    'description' => "Reversa refund #{$refund->refund_number}",
                ];
            }
        }

        // Ajustar redondeo: sumar débitos y créditos
        $totalDebits = array_sum(array_column($lines, 'debit'));
        $totalCredits = array_sum(array_column($lines, 'credit'));
        $diff = round($totalDebits - $totalCredits, 2);

        // Si hay diferencia por redondeo, ajustarla en la primera línea
        if (abs($diff) > 0.001 && !empty($lines)) {
            if ($diff > 0) {
                $lines[0]['credit'] = round($lines[0]['credit'] + $diff, 2);
            } else {
                $lines[0]['debit'] = round($lines[0]['debit'] - $diff, 2);
            }
        }

        return $this->ledgerService->createJournalEntry(
            $payment->company_id,
            $payment->branch_id,
            ReferenceType::REFUND,
            $refund->id,
            $lines,
            "Reembolso #{$refund->refund_number} de pago #{$payment->payment_number}",
            $refund->processed_by
        );
    }

    /**
     * Crea un asiento simple de reversa cuando no hay asiento original.
     * (Fallback para pagos antiguos sin ledger)
     */
    private function createSimpleReversalEntry(Payment $payment, Refund $refund)
    {
        $cashAccount = Account::where('company_id', $payment->company_id)
            ->where('code', '1100')
            ->first();

        $revenueAccount = Account::where('company_id', $payment->company_id)
            ->where('code', '4100')
            ->first();

        if (!$cashAccount || !$revenueAccount) {
            throw InvalidRefundException::missingAccounts();
        }

        $lines = [
            [
                'account_id' => $cashAccount->id,
                'debit' => 0,
                'credit' => (float) $refund->amount,
                'description' => "Reversa refund #{$refund->refund_number}",
            ],
            [
                'account_id' => $revenueAccount->id,
                'debit' => (float) $refund->amount,
                'credit' => 0,
                'description' => "Reversa refund #{$refund->refund_number}",
            ],
        ];

        return $this->ledgerService->createJournalEntry(
            $payment->company_id,
            $payment->branch_id,
            ReferenceType::REFUND,
            $refund->id,
            $lines,
            "Reembolso #{$refund->refund_number} de pago #{$payment->payment_number} (simple)",
            $refund->processed_by
        );
    }

    private function generateRefundNumber(int $companyId): string
    {
        $date = now()->format('Ymd');
        $lastRefund = Refund::where('company_id', $companyId)
            ->whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastRefund ? (intval(substr($lastRefund->refund_number, -4)) + 1) : 1;

        return sprintf('REF-%s-%04d', $date, $sequence);
    }

    /**
     * Obtiene todos los refunds de un payment.
     */
    public function getRefundsForPayment(int $paymentId): array
    {
        return Refund::where('payment_id', $paymentId)
            ->with(['journalEntry.ledgerEntries.account', 'processedBy'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }
}
