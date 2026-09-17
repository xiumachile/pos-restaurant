<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas y columnas a migrar de DECIMAL(14,2) a INTEGER
     * 
     * IMPORTANTE: Los valores DECIMAL(14,2) ya están en formato "12345.67"
     * que representa $12.345,67. Al convertir a INTEGER, debemos multiplicar
     * por 100 para obtener centavos, O simplemente hacer CAST si ya son enteros.
     * 
     * En nuestro caso, como usamos CLP (sin centavos), los valores DECIMAL(14,2)
     * son en realidad "12345.00" (sin parte decimal significativa).
     * Por lo tanto, podemos hacer CAST directo a INTEGER.
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
        ],
        'journal_entries' => [
            'amount',
        ],
        'order_items' => [
            'unit_price_snapshot',
            'subtotal',
            'tax_amount',
        ],
    ];

    /**
     * Columnas que NO deben migrar (porcentajes, tasas)
     */
    protected array $exclude = [
        'taxes.rate',  // DECIMAL(10,4) - tasa de impuesto
    ];

    public function up(): void
    {
        DB::transaction(function () {
            foreach ($this->tables as $table => $columns) {
                foreach ($columns as $column) {
                    $this->convertColumnToInteger($table, $column);
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            foreach ($this->tables as $table => $columns) {
                foreach ($columns as $column) {
                    $this->convertColumnToDecimal($table, $column);
                }
            }
        });
    }

    private function convertColumnToInteger(string $table, string $column): void
    {
        // Paso 1: Eliminar DEFAULT si existe
        DB::statement("
            ALTER TABLE {$table} 
            ALTER COLUMN {$column} DROP DEFAULT
        ");

        // Paso 2: Convertir tipo de DECIMAL a INTEGER
        // USING clause convierte valores existentes
        // Como son CLP (sin centavos), CAST directo es seguro
        DB::statement("
            ALTER TABLE {$table} 
            ALTER COLUMN {$column} TYPE INTEGER 
            USING {$column}::INTEGER
        ");

        // Paso 3: Agregar NOT NULL si la columna no es nullable
        $isNullable = DB::selectOne("
            SELECT is_nullable 
            FROM information_schema.columns 
            WHERE table_name = ? AND column_name = ?
        ", [$table, $column])->is_nullable;

        if ($isNullable === 'NO') {
            DB::statement("
                ALTER TABLE {$table} 
                ALTER COLUMN {$column} SET NOT NULL
            ");
        }

        // Paso 4: Agregar DEFAULT 0 si aplica
        if ($this->shouldHaveDefault($table, $column)) {
            DB::statement("
                ALTER TABLE {$table} 
                ALTER COLUMN {$column} SET DEFAULT 0
            ");
        }

        echo "✅ {$table}.{$column} convertido a INTEGER\n";
    }

    private function convertColumnToDecimal(string $table, string $column): void
    {
        // Revertir: INTEGER → DECIMAL(14,2)
        DB::statement("
            ALTER TABLE {$table} 
            ALTER COLUMN {$column} TYPE DECIMAL(14,2) 
            USING {$column}::DECIMAL(14,2)
        ");

        echo "⏪ {$table}.{$column} revertido a DECIMAL(14,2)\n";
    }

    private function shouldHaveDefault(string $table, string $column): bool
    {
        // Columnas que deben tener DEFAULT 0
        $defaultColumns = [
            'tip_amount',
            'discount_amount',
            'difference',
        ];

        return in_array($column, $defaultColumns);
    }
};
