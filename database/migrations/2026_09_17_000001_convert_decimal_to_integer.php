<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migración completa DECIMAL(14,2) → INTEGER
     * 
     * FIX P1-002: Lee el metadata original (is_nullable, column_default) 
     * ANTES de cualquier modificación para poder restaurarlo fielmente.
     */
    
    protected array $tables = [
        'orders' => ['subtotal', 'subtotal_gross', 'net_amount', 'tax_amount', 'tip_amount', 'discount_amount', 'amount_due', 'total'],
        'bills' => ['subtotal', 'tax_amount', 'tip_amount', 'discount_amount', 'paid_amount', 'remaining_amount', 'total'],
        'payments' => ['amount', 'tip_amount', 'total_amount'],
        'cash_sessions' => ['opening_amount', 'closing_amount', 'expected_amount', 'difference'],
        'cash_movements' => ['amount', 'balance_after'],
        'cash_counts' => ['card_amount', 'cash_amount', 'counted_amount', 'difference', 'expected_amount', 'other_amount', 'transfer_amount'],
        'refunds' => ['amount'],
        'tip_payouts' => ['amount'],
        'journal_entries' => ['amount'],
        'ledger_entries' => ['debit_amount', 'credit_amount'],
        'order_items' => ['unit_price_snapshot', 'subtotal', 'tax_amount'],
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
        // PASO 0: Leer metadata ORIGINAL antes de cualquier modificación (FIX P1-002)
        $originalColumn = DB::selectOne("
            SELECT data_type, is_nullable, column_default
            FROM information_schema.columns 
            WHERE table_name = ? AND column_name = ?
        ", [$table, $column]);

        if (!$originalColumn) {
            echo "⚠️  Columna {$table}.{$column} no existe, saltando\n";
            return;
        }

        if ($originalColumn->data_type === 'integer' || $originalColumn->data_type === 'bigint') {
            echo "✅ {$table}.{$column} ya es INTEGER, saltando\n";
            return;
        }

        $wasNullable = $originalColumn->is_nullable === 'YES';
        $originalDefault = $originalColumn->column_default;

        try {
            // PASO 1: Drop DEFAULT si existe
            if ($originalDefault !== null) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP DEFAULT");
            }

            // PASO 2: Drop NOT NULL temporalmente (SOLO si era NOT NULL)
            if (!$wasNullable) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP NOT NULL");
            }

            // PASO 3: Convertir tipo (usando ROUND para evitar truncamiento silencioso)
            DB::statement("
                ALTER TABLE {$table} 
                ALTER COLUMN {$column} TYPE INTEGER 
                USING (CASE WHEN {$column} IS NULL THEN 0 ELSE ROUND({$column})::INTEGER END)
            ");

            // PASO 4: Restaurar NOT NULL si originalmente era NOT NULL
            if (!$wasNullable) {
                DB::statement("UPDATE {$table} SET {$column} = 0 WHERE {$column} IS NULL");
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET NOT NULL");
            }

            // PASO 5: Restaurar DEFAULT original o aplicar nuevo DEFAULT si es necesario
            if ($originalDefault !== null) {
                // Limpiar el default de PostgreSQL (ej: "'0'::numeric" -> 0)
                $cleanDefault = is_numeric($originalDefault) ? (int)round((float)$originalDefault) : 0;
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT {$cleanDefault}");
            } elseif ($this->shouldHaveDefault($column)) {
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
        // FIX P1-002 (Down): También leer metadata antes de modificar en el rollback
        $originalColumn = DB::selectOne("
            SELECT is_nullable
            FROM information_schema.columns 
            WHERE table_name = ? AND column_name = ?
        ", [$table, $column]);

        $wasNullable = $originalColumn ? ($originalColumn->is_nullable === 'YES') : true;

        try {
            if (!$wasNullable) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP NOT NULL");
            }

            DB::statement("
                ALTER TABLE {$table} 
                ALTER COLUMN {$column} TYPE DECIMAL(14,2) 
                USING {$column}::DECIMAL(14,2)
            ");

            if (!$wasNullable) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET NOT NULL");
            }
            
            echo "⏪ {$table}.{$column} revertido a DECIMAL(14,2)\n";
        } catch (\Exception $e) {
            echo "❌ ERROR revertiendo {$table}.{$column}: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    private function shouldHaveDefault(string $column): bool
    {
        return in_array($column, [
            'tip_amount', 'discount_amount', 'difference', 'tax_amount', 'debit_amount', 'credit_amount',
        ]);
    }
};
