<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ADR-018: Money integrity constraints + data correction.
 *
 * Esta migración resuelve dos problemas en un solo paso:
 *
 * FASE 1 - CORRECCIÓN DE DATOS (P0):
 * 56 bills tenían `paid_amount = 2 × total` por un bug histórico,
 * causando `remaining_amount` negativo. Corregimos paid_amount
 * calculando SUM(amount) de payments completed (fuente de verdad).
 *
 * FASE 2 - CHECK CONSTRAINTS (P1):
 * Agregamos constraints `CHECK (column >= 0)` a columnas monetarias
 * que NO deberían tener valores negativos por diseño.
 *
 * Decisiones de diseño:
 * - journal_entries: NO tiene columna `amount` (monto está en ledger_entries)
 * - remaining_amount: PUEDE ser negativo (sobrepagos, reembolsos)
 * - difference: PUEDE ser negativo (faltante/sobrante de caja)
 * - Distribución de dígitos: no se audita (datos demo redondos son válidos)
 *
 * Ref: docs/architecture/money-integrity.md
 */
return new class extends Migration
{
    /**
     * FASE 1: Corregir inconsistencia en bills.paid_amount con auditoría financiera explícita.
     * 
     * P1: La migración anterior solo sumaba payments.completed, ignorando refunds.
     * Esto podía causar over-corrección si un pago fue reembolsado.
     * 
     * Fórmula financiera correcta:
     * new_paid = SUM(payments.amount WHERE status = 'completed') 
     *            - SUM(refunds.amount WHERE status = 'completed' AND payment.bill_id = bill.id)
     */
    private function correctPaidAmountInconsistency(): void
    {
        Log::info("[MoneyIntegrity] Iniciando auditoría y corrección de paid_amount...");

        // Consulta financiera auditable por bill
        $billsAudit = DB::table('bills as b')
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
                'b.uuid',
                'b.total',
                'b.paid_amount as old_paid',
                'b.remaining_amount as old_remaining',
                DB::raw('COALESCE(SUM(DISTINCT p.amount), 0) as completed_payments'),
                DB::raw('COALESCE(SUM(r.amount), 0) as completed_refunds')
            )
            ->groupBy('b.id', 'b.uuid', 'b.total', 'b.paid_amount', 'b.remaining_amount')
            ->get();

        $correctedCount = 0;
        $auditReport = [];

        foreach ($billsAudit as $bill) {
            // Fórmula financiera auditable
            $newPaid = max(0, (int) $bill->completed_payments - (int) $bill->completed_refunds);
            $newRemaining = max(0, (int) $bill->total - $newPaid);

            // Solo actualizar si hay una diferencia real (evitar updates innecesarios)
            if ((int) $bill->old_paid !== $newPaid || (int) $bill->old_remaining !== $newRemaining) {
                
                DB::table('bills')
                    ->where('id', $bill->id)
                    ->update([
                        'paid_amount' => $newPaid,
                        'remaining_amount' => $newRemaining,
                        'updated_at' => now(),
                    ]);

                $correctedCount++;
                
                // Registrar auditoría detallada para trazabilidad financiera
                $auditLog = [
                    'bill_id' => $bill->id,
                    'bill_uuid' => $bill->uuid,
                    'total' => $bill->total,
                    'old_paid' => $bill->old_paid,
                    'old_remaining' => $bill->old_remaining,
                    'completed_payments' => $bill->completed_payments,
                    'completed_refunds' => $bill->completed_refunds,
                    'new_paid' => $newPaid,
                    'new_remaining' => $newRemaining,
                    'difference' => $newPaid - $bill->old_paid,
                ];
                
                $auditReport[] = $auditLog;
                Log::warning("[MoneyIntegrity] Bill corregido: #{$bill->id}", $auditLog);
            }
        }

        Log::info("[MoneyIntegrity] Auditoría finalizada. Bills corregidos: {$correctedCount}");
        
        // Guardar reporte completo en un archivo de log separado para revisión financiera
        if ($correctedCount > 0) {
            $reportPath = storage_path('logs/money_integrity_audit_' . date('Y-m-d_His') . '.json');
            file_put_contents($reportPath, json_encode($auditReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            Log::info("[MoneyIntegrity] Reporte de auditoría guardado en: {$reportPath}");
        }
    }

    /**
     * FASE 2: Agregar CHECK CONSTRAINTS non-negative
     */
    private function addNonNegativeConstraints(): void
    {
        $addedCount = 0;

        foreach ($this->nonNegativeConstraints as $table => $columns) {
            if (!$this->tableExists($table)) {
                Log::warning("[MoneyIntegrity] Tabla {$table} no existe, saltando");
                continue;
            }

            foreach ($columns as $column) {
                if (!$this->columnExists($table, $column)) {
                    Log::warning("[MoneyIntegrity] Columna {$table}.{$column} no existe, saltando");
                    continue;
                }

                $constraintName = $this->constraintName($table, $column);

                // Verificar que no haya valores negativos actuales
                $negativeCount = DB::table($table)->where($column, '<', 0)->count();
                if ($negativeCount > 0) {
                    $message = "[MoneyIntegrity] FALLA: {$table}.{$column} tiene {$negativeCount} valores negativos. Corregir datos antes de aplicar constraint.";
                    Log::error($message);
                    throw new \RuntimeException($message);
                }

                // Eliminar constraint previo (idempotencia)
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraintName}");

                // Agregar constraint
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraintName} CHECK ({$column} >= 0)");

                $addedCount++;
            }
        }

        Log::info("[MoneyIntegrity] {$addedCount} CHECK constraints agregados");
    }

    private function constraintName(string $table, string $column): string
    {
        return "chk_{$table}_{$column}_non_negative";
    }

    private function tableExists(string $table): bool
    {
        return DB::selectOne(
            "SELECT 1 FROM information_schema.tables WHERE table_name = ? AND table_schema = 'public'",
            [$table]
        ) !== null;
    }

    private function columnExists(string $table, string $column): bool
    {
        return DB::selectOne(
            "SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ?",
            [$table, $column]
        ) !== null;
    }
};
