<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
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
        // FIX P1-003: Columnas monetarias faltantes
        'products' => ['base_price'],
        'product_prices' => ['price'],
        'menu_items' => ['base_price'],
        'payment_methods' => ['max_amount'],
        'cash_registers' => ['max_amount'],
        'order_item_modifiers' => ['price_adjustment'],
        'menu_item_replacement_rules' => ['max_price_delta'],
    ];

    public function up(): void
    {

    // ========================================================================
    // P1-001/P1-002: PRE-FLIGHT CHECK DE INTEGRIDAD FINANCIERA
    // Abortar la migración si se detectan valores fraccionarios.
    // El redondeo silencioso está PROHIBIDO en migraciones monetarias.
    // ========================================================================
    foreach ($this->tables as $table => $columns) {
        // Verificar si la tabla existe antes de consultar
        $tableExists = DB::selectOne("SELECT 1 FROM information_schema.tables WHERE table_name = '{$table}'");
        if (!$tableExists) {
            continue;
        }

        foreach ($columns as $column) {
            // Verificar si la columna existe
            $columnExists = DB::selectOne("SELECT 1 FROM information_schema.columns WHERE table_name = '{$table}' AND column_name = '{$column}'");
            if (!$columnExists) {
                continue;
            }

            // Buscar valores que no sean enteros exactos
            $fractionalRecords = DB::selectOne("
                SELECT COUNT(*) as count 
                FROM {$table} 
                WHERE {$column} IS NOT NULL 
                AND {$column} != CAST(ROUND({$column}) AS INTEGER)
            ");

            if ($fractionalRecords->count > 0) {
                throw new \RuntimeException(
                    "P1-001/P1-002: MIGRACIÓN FINANCIERA ABORTADA. " .
                    "Se detectaron {$fractionalRecords->count} registros con valores fraccionarios " .
                    "en la columna '{$column}' de la tabla '{$table}'. " .
                    "Corrija los datos manualmente a valores enteros (centavos) antes de ejecutar esta migración."
                );
            }
        }
    }
    // ========================================================================

        foreach ($this->tables as $table => $columns) {
            foreach ($columns as $column) {
                $this->convertColumnToInteger($table, $column);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $columns) {
            foreach (array_reverse($columns) as $column) {
                $this->convertColumnToDecimal($table, $column);
            }
        }
    }

    private function convertColumnToInteger(string $table, string $column): void
    {
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
            if ($originalDefault !== null && trim((string)$originalDefault) !== 'NULL') {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP DEFAULT");
            }

            // PASO 2: Drop NOT NULL temporalmente
            if (!$wasNullable) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP NOT NULL");
            }

            // PASO 3: Limpiar valores NULL a 0 SOLO si la columna era originalmente NOT NULL
            if (!$wasNullable) {
                DB::statement("UPDATE {$table} SET {$column} = 0 WHERE {$column} IS NULL");
            }

            // PASO 4: Convertir tipo
            DB::statement("
                ALTER TABLE {$table} 
                ALTER COLUMN {$column} TYPE INTEGER 
                USING (CASE WHEN {$column} IS NULL THEN NULL ELSE ROUND({$column})::INTEGER END)
            ");

            // PASO 5: Restaurar NOT NULL si originalmente era NOT NULL
            if (!$wasNullable) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET NOT NULL");
            }

            // PASO 6: Aplicar DEFAULT
            // Si era NOT NULL y no tenía default numérico, aplicar DEFAULT 0
            // Si tenía default numérico, restaurarlo
            if (!$wasNullable) {
                if ($originalDefault !== null && trim((string)$originalDefault) !== 'NULL' && is_numeric($originalDefault)) {
                    $defaultVal = (int)round((float)$originalDefault);
                } else {
                    $defaultVal = 0;
                }
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT {$defaultVal}");
            }

            echo "✅ {$table}.{$column} convertido a INTEGER\n";
        } catch (\Exception $e) {
            echo "❌ ERROR en {$table}.{$column}: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    private function convertColumnToDecimal(string $table, string $column): void
    {
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
};
