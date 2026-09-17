<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migración completa DECIMAL(14,2) → INTEGER
     * 
     * Versión robusta: procesa cada columna individualmente con try/catch
     * para identificar exactamente qué columna falla.
     */
    
    protected array $tables = [
        'orders' => [
            'subtotal',
            'subtotal_gross',
            'net_amount',
            'tax_amount',
            'tip_amount',
            'discount_amount',
            'amount_due',
            'total',
        ],
        'bills' => [
            'subtotal',
            'tax_amount',
            'tip_amount',
            'discount_amount',
            'paid_amount',
            'remaining_amount',
            'total',
        ],
        'payments' => [
            'amount',
            'tip_amount',
            'total_amount',
        ],
        'cash_sessions' => [
            'opening_amount',
            'closing_amount',
            'expected_amount',
            'difference',
        ],
        'cash_movements' => [
            'amount',
            'balance_after',
        ],
        'cash_counts' => [
            'card_amount',
            'cash_amount',
            'counted_amount',
            'difference',
            'expected_amount',
            'other_amount',
            'transfer_amount',
        ],
        'refunds' => [
            'amount',
        ],
        'tip_payouts' => [
            'amount',
        ],
        'journal_entries' => [
            'amount',
        ],
        'ledger_entries' => [
            'debit_amount',
            'credit_amount',
        ],
        'order_items' => [
            'unit_price_snapshot',
            'subtotal',
            'tax_amount',
        ],
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $columns) {
            foreach ($columns as $column) {
                $this->convertColumnToInteger($table, $column);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $columns) {
            foreach ($columns as $column) {
                $this->convertColumnToDecimal($table, $column);
            }
        }
    }

    private function convertColumnToInteger(string $table, string $column): void
    {
        // Verificar si la columna ya es INTEGER
        $currentType = DB::selectOne("
            SELECT data_type 
            FROM information_schema.columns 
            WHERE table_name = ? AND column_name = ?
        ", [$table, $column]);

        if (!$currentType) {
            echo "⚠️  Columna {$table}.{$column} no existe, saltando\n";
            return;
        }

        if ($currentType->data_type === 'integer' || $currentType->data_type === 'bigint') {
            echo "✅ {$table}.{$column} ya es INTEGER, saltando\n";
            return;
        }

        try {
            // Paso 1: Drop DEFAULT si existe
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP DEFAULT");

            // Paso 2: Drop NOT NULL temporalmente (para permitir conversión)
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP NOT NULL");

            // Paso 3: Convertir tipo
            DB::statement("
                ALTER TABLE {$table} 
                ALTER COLUMN {$column} TYPE INTEGER 
                USING (CASE WHEN {$column} IS NULL THEN 0 ELSE {$column}::INTEGER END)
            ");

            // Paso 4: Restaurar NOT NULL si era NOT NULL originalmente
            $isNullable = DB::selectOne("
                SELECT is_nullable 
                FROM information_schema.columns 
                WHERE table_name = ? AND column_name = ?
            ", [$table, $column])->is_nullable;

            if ($isNullable === 'NO') {
                // Primero actualizar cualquier NULL a 0
                DB::statement("UPDATE {$table} SET {$column} = 0 WHERE {$column} IS NULL");
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET NOT NULL");
            }

            // Paso 5: Set DEFAULT 0 para columnas que lo necesitan
            if ($this->shouldHaveDefault($column)) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT 0");
            }

            echo "✅ {$table}.{$column} convertido a INTEGER\n";
        } catch (\Exception $e) {
            echo "❌ ERROR en {$table}.{$column}: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    private function convertColumnToDecimal(string $table, string $column): void
    {
        try {
            DB::statement("
                ALTER TABLE {$table} 
                ALTER COLUMN {$column} TYPE DECIMAL(14,2) 
                USING {$column}::DECIMAL(14,2)
            ");
            echo "⏪ {$table}.{$column} revertido a DECIMAL(14,2)\n";
        } catch (\Exception $e) {
            echo "❌ ERROR revertiendo {$table}.{$column}: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    private function shouldHaveDefault(string $column): bool
    {
        return in_array($column, [
            'tip_amount',
            'discount_amount',
            'difference',
            'tax_amount',
            'debit_amount',
            'credit_amount',
        ]);
    }
};
