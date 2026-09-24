<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\Refund;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * P1 Adicional: Prueba financiera de auditoría para migración de paid_amount.
 * 
 * Demuestra que la fórmula:
 * new_paid = completed_payments - completed_refunds
 * new_remaining = total - new_paid
 * 
 * Representa correctamente la semántica financiera del sistema, incluso ante
 * pagos múltiples y reembolsos parciales o totales.
 */
beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'AUDIT-' . uniqid(),
        'legal_name' => 'Money Integrity Audit Test',
        'trade_name' => 'Audit Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'AUDIT-' . uniqid(),
        'name' => 'Audit Branch',
    ]);

    $this->user = User::create([
        'name' => 'Audit Tester',
        'email' => 'audit-' . uniqid() . '@test.com',
        'password' => bcrypt('password123'),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    $this->cashMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'cash-' . uniqid(),
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    $this->order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => 'draft',
        'waiter_id' => $this->user->id,
    ]);
});

test('fórmula financiera calcula correctamente paid y remaining con pagos y reembolsos parciales', function () {
    // 1. Crear una bill con total de 10000 (100.00 CLP en enteros)
    $bill = Bill::create([
        'uuid' => Str::uuid()->toString(),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $this->order->id,
        'bill_number' => 'BILL-' . uniqid(),
        'type' => 'equal_split',
        'subtotal' => 8403,
        'tax_amount' => 1597,
        'discount_amount' => 0,
        'tip_amount' => 0,
        'total' => 10000,
        'paid_amount' => 0,
        'remaining_amount' => 10000,
        'status' => 'open',
    ]);

    // 2. Crear dos pagos completados (total 8000)
    $payment1 = Payment::create([
        'uuid' => Str::uuid()->toString(),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $this->order->id,
        'bill_id' => $bill->id,
        'payment_method_id' => $this->cashMethod->id,
        'user_id' => $this->user->id,
        'payment_number' => 'PAY-' . uniqid(),
        'method_code' => 'cash',
        'amount' => 5000,
        'tip_amount' => 0,
        'total_amount' => 5000,
        'status' => 'completed',
        'idempotency_key' => Str::uuid()->toString(),
    ]);

    $payment2 = Payment::create([
        'uuid' => Str::uuid()->toString(),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $this->order->id,
        'bill_id' => $bill->id,
        'payment_method_id' => $this->cashMethod->id,
        'user_id' => $this->user->id,
        'payment_number' => 'PAY-' . uniqid(),
        'method_code' => 'card',
        'amount' => 3000,
        'tip_amount' => 0,
        'total_amount' => 3000,
        'status' => 'completed',
        'idempotency_key' => Str::uuid()->toString(),
    ]);

    // 3. Crear un reembolso completado sobre el primer pago (reembolso parcial de 2000)
    $refund1 = Refund::create([
        'uuid' => Str::uuid()->toString(),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'payment_id' => $payment1->id,
        'refund_number' => 'REF-' . uniqid(),
        'amount' => 2000,
        'status' => 'completed',
        'processed_at' => now(),
        'processed_by' => $this->user->id,
        'idempotency_key' => Str::uuid()->toString(),
    ]);

    // 4. Simular la lógica de la migración (auditoría financiera)
    $auditData = \DB::table('bills as b')
        ->leftJoin('payments as p', function ($join) {
            $join->on('p.bill_id', '=', 'b.id')
                 ->where('p.status', '=', 'completed')
                 ->whereNull('p.deleted_at');
        })
        ->leftJoin('refunds as r', function ($join) {
            $join->on('r.payment_id', '=', 'p.id')
                 ->where('r.status', '=', 'completed')
                 ->whereNull('r.deleted_at');
        })
        ->select(
            'b.id',
            'b.total',
            'b.paid_amount as old_paid',
            'b.remaining_amount as old_remaining',
            \DB::raw('COALESCE(SUM(DISTINCT p.amount), 0) as completed_payments'),
            \DB::raw('COALESCE(SUM(r.amount), 0) as completed_refunds')
        )
        ->where('b.id', $bill->id)
        ->groupBy('b.id', 'b.total', 'b.paid_amount', 'b.remaining_amount')
        ->first();

    // 5. Aplicar fórmula financiera
    $newPaid = max(0, (int) $auditData->completed_payments - (int) $auditData->completed_refunds);
    $newRemaining = max(0, (int) $auditData->total - $newPaid);

    // 6. Aserciones financieras
    expect($auditData->completed_payments)->toBe(8000, 'La suma de pagos completados debe ser 8000');
    expect($auditData->completed_refunds)->toBe(2000, 'La suma de reembolsos completados debe ser 2000');
    expect($newPaid)->toBe(6000, 'El nuevo paid_amount debe ser 6000 (pagos - reembolsos)');
    expect($newRemaining)->toBe(4000, 'El nuevo remaining_amount debe ser 4000 (total - new_paid)');
    
    // 7. Verificar que el invariante se mantiene: paid + remaining = total
    expect($newPaid + $newRemaining)->toBe($auditData->total, 'El invariante financiero (paid + remaining = total) debe mantenerse siempre');
});

test('fórmula financiera maneja correctamente reembolso total', function () {
    $bill = Bill::create([
        'uuid' => Str::uuid()->toString(),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $this->order->id,
        'bill_number' => 'BILL-' . uniqid(),
        'type' => 'equal_split',
        'total' => 5000,
        'paid_amount' => 5000,
        'remaining_amount' => 0,
        'status' => 'paid',
    ]);

    $payment = Payment::create([
        'uuid' => Str::uuid()->toString(),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $this->order->id,
        'bill_id' => $bill->id,
        'payment_method_id' => $this->cashMethod->id,
        'user_id' => $this->user->id,
        'payment_number' => 'PAY-' . uniqid(),
        'method_code' => 'cash',
        'amount' => 5000,
        'tip_amount' => 0,
        'total_amount' => 5000,
        'status' => 'completed',
        'idempotency_key' => Str::uuid()->toString(),
    ]);

    // Reembolso total
    Refund::create([
        'uuid' => Str::uuid()->toString(),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'payment_id' => $payment->id,
        'refund_number' => 'REF-' . uniqid(),
        'amount' => 5000,
        'status' => 'completed',
        'processed_at' => now(),
        'processed_by' => $this->user->id,
        'idempotency_key' => Str::uuid()->toString(),
    ]);

    $auditData = \DB::table('bills as b')
        ->leftJoin('payments as p', function ($join) {
            $join->on('p.bill_id', '=', 'b.id')->where('p.status', '=', 'completed')->whereNull('p.deleted_at');
        })
        ->leftJoin('refunds as r', function ($join) {
            $join->on('r.payment_id', '=', 'p.id')->where('r.status', '=', 'completed')->whereNull('r.deleted_at');
        })
        ->select(
            'b.total',
            \DB::raw('COALESCE(SUM(DISTINCT p.amount), 0) as completed_payments'),
            \DB::raw('COALESCE(SUM(r.amount), 0) as completed_refunds')
        )
        ->where('b.id', $bill->id)
        ->groupBy('b.id', 'b.total')
        ->first();

    $newPaid = max(0, (int) $auditData->completed_payments - (int) $auditData->completed_refunds);
    $newRemaining = max(0, (int) $auditData->total - $newPaid);

    expect($newPaid)->toBe(0, 'Tras reembolso total, paid_amount debe ser 0');
    expect($newRemaining)->toBe(5000, 'Tras reembolso total, remaining_amount debe volver al total original');
});
