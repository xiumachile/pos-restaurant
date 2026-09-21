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
     * Columnas monetarias con constraint non-negative.
     * Solo incluimos columnas que REALMENTE no deberían ser negativas.
     */
    protected array $nonNegativeConstraints = [
        'orders' => ['subtotal', 'tax_amount', 'discount_amount', 'tip_amount', 'total'],
        'order_items' => ['unit_price_snapshot', 'subtotal', 'tax_amount'],
        'bills' => ['subtotal', 'total', 'tax_amount', 'tip_amount', 'discount_amount', 'paid_amount'],
        'payments' => ['amount', 'tip_amount', 'total_amount'],
        'cash_sessions' => ['opening_amount', 'closing_amount', 'expected_amount'],
        'cash_movements' => ['amount'],
        'cash_counts' => ['card_amount', 'cash_amount', 'counted_amount', 'expected_amount', 'other_amount', 'transfer_amount'],
        'refunds' => ['amount'],
        'tip_payouts' => ['amount'],
        'ledger_entries' => ['debit_amount', 'credit_amount'],
        // journal_entries: NO incluido - no tiene columna amount
    ];

    /**
     * Columnas que PUEDEN ser negativas por diseño:
     * - cash_sessions.difference: sobrante/faltante
     * - cash_counts.difference: sobrante/faltante
     * - bills.remaining_amount: sobrepagos permitidos
     * - cash_movements.balance_after: depende del flujo
     */

    public function up(): void
    {
        DB::transaction(function () {
            $this->correctPaidAmountInconsistency();
            $this->addNonNegativeConstraints();
        });

        Log::info('[MoneyIntegrity] Migración completada exitosamente');
    }

    public function down(): void
    {
        Log::info('[MoneyIntegrity] Revirtiendo constraints (datos no se revierten)');

        foreach ($this->nonNegativeConstraints as $table => $columns) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($columns as $column) {
                $constraintName = $this->constraintName($table, $column);
                try {
                    DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraintName}");
                } catch (\Exception $e) {
                    Log::warning("[MoneyIntegrity] No se pudo eliminar constraint {$constraintName}");
                }
            }
        }

        Log::info('[MoneyIntegrity] Constraints eliminados (paid_amount NO se revierte - datos ya corregidos)');
    }

    /**
     * FASE 1: Corregir inconsistencia en bills.paid_amount
     *
     * Problema: 56 bills tenían paid_amount = 2 × total, causando
     * remaining_amount negativo. La fuente de verdad son los payments
     * (tabla `payments` con `status = completed`).
     */
    private function correctPaidAmountInconsistency(): void
    {
        // Identificar bills con paid_amount incorrecto
        $billsWithInconsistency = DB::table('bills as b')
            ->leftJoin('payments as p', function ($join) {
                $join->on('p.bill_id', '=', 'b.id')
                     ->where('p.status', '=', 'completed');
            })
            ->select(
                'b.id',
                'b.total',
                'b.paid_amount as current_paid',
                DB::raw('COALESCE(SUM(p.amount), 0) as correct_paid')
            )
            ->groupBy('b.id', 'b.total', 'b.paid_amount')
            ->havingRaw('b.paid_amount <> COALESCE(SUM(p.amount), 0)')
            ->get();

        $correctedCount = 0;

        foreach ($billsWithInconsistency as $bill) {
            // Calcular remaining_amount correcto
            $correctRemaining = $bill->total - $bill->correct_paid;

            DB::table('bills')
                ->where('id', $bill->id)
                ->update([
                    'paid_amount' => $bill->correct_paid,
                    'remaining_amount' => $correctRemaining,
                    'updated_at' => now(),
                ]);

            $correctedCount++;

            Log::info("[MoneyIntegrity] Bill #{$bill->id}: paid_amount {$bill->current_paid} → {$bill->correct_paid}, remaining {$correctRemaining}");
        }

        if ($correctedCount > 0) {
            Log::info("[MoneyIntegrity] {$correctedCount} bills corregidos (paid_amount = SUM payments)");
        } else {
            Log::info('[MoneyIntegrity] No hay bills con paid_amount incorrecto');
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
