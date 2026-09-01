<?php

namespace Modules\Payments\Domain\Services;

use Modules\Accounting\Domain\Entities\Account;
use Modules\Accounting\Domain\Services\LedgerService;
use Modules\Accounting\Domain\ValueObjects\ReferenceType;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Exceptions\PaymentException;

/**
 * Servicio que genera asientos contables para pagos.
 * 
 * RESPONSABILIDADES:
 * - Mapear paymentMethod.type → cuenta contable destino
 * - Calcular proporciones para pagos parciales
 * - Distribuir propinas a TipsPayable (2200)
 * - Distribuir descuentos a Descuentos (4200)
 * - Ajustar por redondeo en la primera línea
 * 
 * INTEGRACIÓN:
 * Es llamado por PaymentService.registerPayment() DESPUÉS de crear el payment
 * y dentro de la misma transacción (si el asiento falla, se revierte el payment).
 * 
 * IDEMPOTENCIA:
 * Solo se llama cuando se crea un payment NUEVO (no en caso de idempotencia),
 * porque PaymentService retorna el payment existente sin invocar este servicio.
 */
class PaymentLedgerService
{
    public function __construct(
        private LedgerService $ledgerService
    ) {}

    /**
     * Genera asiento contable para un payment.
     * 
     * EJEMPLO: Pago de $5,000 (cash) + $500 (propina) de un order $11,900
     * (subtotal $10,000 + IVA $1,900)
     * 
     * Ratio = 5000/11900 = 0.4202
     * 
     * JournalEntry:
     * ├── DEBIT  Cash (1100)         $5,500
     * ├── CREDIT Revenue (4100)      $4,202
     * ├── CREDIT TaxPayable (2100)     $798
     * └── CREDIT TipsPayable (2200)    $500
     * 
     * @throws PaymentException si faltan cuentas contables requeridas
     */
    public function recordPayment(Payment $payment): void
    {
        $order = $payment->order;
        
        if (!$order) {
            throw PaymentException::orderRequiredForLedger();
        }

        // Calcular proporción del pago respecto al total del pedido
        $orderTotal = (float) $order->total;
        $paymentAmount = (float) $payment->amount;
        $tipAmount = (float) $payment->tip_amount;
        
        if ($orderTotal <= 0) {
            throw PaymentException::invalidOrderTotal($orderTotal);
        }

        $ratio = $paymentAmount / $orderTotal;

        // Resolver cuentas contables requeridas
        $accounts = $this->resolveAccounts($payment);

        // Calcular líneas de asiento con proporciones
        $lines = $this->buildLedgerLines(
            $payment,
            $accounts,
            $order,
            $ratio,
            $paymentAmount,
            $tipAmount
        );

        // Crear asiento contable
        $this->ledgerService->createJournalEntry(
            $payment->company_id,
            $payment->branch_id,
            ReferenceType::PAYMENT,
            $payment->id,
            $lines,
            "Pago #{$payment->payment_number} ({$payment->method_code})",
            $payment->user_id
        );
    }

    /**
     * Resuelve las cuentas contables necesarias según el método de pago.
     * 
     * @throws PaymentException si faltan cuentas requeridas
     */
    private function resolveAccounts(Payment $payment): array
    {
        $companyId = $payment->company_id;

        // Cuenta destino según método de pago
        $destinationCode = match($payment->method_code) {
            'cash' => '1100',      // Efectivo en Caja
            'card' => '1300',      // Por Cobrar Tarjetas
            'transfer' => '1200',  // Bancos
            'gift_card' => '1400', // Gift Cards por Canjear
            default => '1100',     // Fallback: efectivo
        };

        // NOTA: NO llamar seedDefaultsFor aquí porque este método se ejecuta
        // DENTRO de DB::transaction. El seed ya se hizo en PaymentService
        // ANTES de abrir la transacción para evitar PostgreSQL 25P02.
        // Si las cuentas no existen, simplemente fallará con mensaje claro.
        return [
            'destination' => Account::findByCodeOrFail($companyId, $destinationCode),
            'revenue' => Account::findByCodeOrFail($companyId, '4100'),
            'tax' => Account::findByCodeOrFail($companyId, '2100'),
            'tips' => Account::findByCodeOrFail($companyId, '2200'),
            'discount' => Account::findByCodeOrFail($companyId, '4200'),
        ];
    }

    /**
     * Construye las líneas del asiento contable con cálculo proporcional.
     */
    private function buildLedgerLines(
        Payment $payment,
        array $accounts,
        $order,
        float $ratio,
        float $paymentAmount,
        float $tipAmount
    ): array {
        $orderSubtotal = (float) $order->subtotal;
        $orderTax = (float) $order->tax_amount;
        $orderDiscount = (float) ($order->discount_amount ?? 0);

        $lines = [];

        // LÍNEA 1: Débito a cuenta destino (amount + tip)
        $totalReceived = $paymentAmount + $tipAmount;
        $lines[] = [
            'account_id' => $accounts['destination']->id,
            'debit' => $totalReceived,
            'credit' => 0,
            'description' => "Pago #{$payment->payment_number} recibido",
        ];

        // LÍNEA 2: Crédito a Revenue (proporcional al subtotal)
        $revenueCredit = round($orderSubtotal * $ratio, 2);
        if ($revenueCredit > 0) {
            $lines[] = [
                'account_id' => $accounts['revenue']->id,
                'debit' => 0,
                'credit' => $revenueCredit,
                'description' => "Ingreso proporcional ({$ratio})",
            ];
        }

        // LÍNEA 3: Crédito a TaxPayable (proporcional al IVA)
        $taxCredit = round($orderTax * $ratio, 2);
        if ($taxCredit > 0) {
            $lines[] = [
                'account_id' => $accounts['tax']->id,
                'debit' => 0,
                'credit' => $taxCredit,
                'description' => "IVA proporcional ({$ratio})",
            ];
        }

        // LÍNEA 4: Débito a Discount (si hay descuentos, reducir ingreso)
        // Los descuentos se manejan como reducción del revenue
        $discountDebit = round($orderDiscount * $ratio, 2);
        if ($discountDebit > 0) {
            $lines[] = [
                'account_id' => $accounts['discount']->id,
                'debit' => $discountDebit,
                'credit' => 0,
                'description' => "Descuento proporcional ({$ratio})",
            ];
        }

        // LÍNEA 5: Crédito a TipsPayable (propina, NO proporcional)
        if ($tipAmount > 0) {
            $lines[] = [
                'account_id' => $accounts['tips']->id,
                'debit' => 0,
                'credit' => $tipAmount,
                'description' => "Propina del cliente",
            ];
        }

        // Ajuste por redondeo en la primera línea (destination)
        $totalDebits = array_sum(array_column($lines, 'debit'));
        $totalCredits = array_sum(array_column($lines, 'credit'));
        $diff = round($totalDebits - $totalCredits, 2);

        if (abs($diff) > 0.001 && !empty($lines)) {
            // Ajustar la primera línea (destination)
            if ($diff > 0) {
                $lines[0]['credit'] = round($lines[0]['credit'] + $diff, 2);
            } else {
                $lines[0]['debit'] = round($lines[0]['debit'] - $diff, 2);
            }
        }

        return $lines;
    }
}
