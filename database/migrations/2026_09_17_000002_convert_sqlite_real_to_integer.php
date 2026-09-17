<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migración de SQLite: REAL → INTEGER
     * 
     * SQLite no soporta ALTER COLUMN, así que debemos:
     * 1. Crear tabla temporal con schema nuevo
     * 2. Copiar datos (con conversión)
     * 3. Eliminar tabla original
     * 4. Renombrar tabla temporal
     */
    
    protected array $tables = [
        'local_orders' => [
            'subtotal',
            'subtotal_gross',
            'net_amount',
            'tax_amount',
            'tip_amount',
            'discount_amount',
            'amount_due',
            'total',
        ],
        'local_bills' => [
            'subtotal',
            'tax_amount',
            'tip_amount',
            'discount_amount',
            'paid_amount',
            'remaining_amount',
            'total',
        ],
        'local_payments' => [
            'amount',
            'tip_amount',
            'total_amount',
        ],
        'local_cash_sessions' => [
            'opening_amount',
            'closing_amount',
            'expected_amount',
            'difference',
        ],
        'local_cash_movements' => [
            'amount',
        ],
        'local_order_items' => [
            'unit_price_snapshot',
            'subtotal',
            'tax_amount',
        ],
    ];

    public function up(): void
    {
        // Esta migración solo aplica si estamos en SQLite
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        foreach ($this->tables as $table => $columns) {
            $this->convertTableToInteger($table, $columns);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        // Nota: down() es complejo en SQLite porque requiere recrear tablas
        // Para producción, esta migración es irreversible
        // En desarrollo, se puede hacer drop y re-migrate
        throw new \Exception('Esta migración es irreversible en SQLite. Use migrate:fresh si necesita revertir.');
    }

    private function convertTableToInteger(string $table, array $columns): void
    {
        // Verificar que la tabla existe
        $tableExists = DB::selectOne("
            SELECT name FROM sqlite_master 
            WHERE type='table' AND name=?
        ", [$table]);

        if (!$tableExists) {
            echo "⚠️  Tabla {$table} no existe, saltando\n";
            return;
        }

        // Obtener schema actual
        $schema = DB::selectOne("
            SELECT sql FROM sqlite_master 
            WHERE type='table' AND name=?
        ", [$table])->sql;

        // Crear nuevo schema con INTEGER en lugar de REAL
        $newSchema = $schema;
        foreach ($columns as $column) {
            $newSchema = preg_replace(
                "/{$column}\s+REAL/i",
                "{$column} INTEGER",
                $newSchema
            );
        }

        // Renombrar tabla original
        $tempTable = "{$table}_old";
        DB::statement("ALTER TABLE {$table} RENAME TO {$tempTable}");

        // Crear nueva tabla con schema actualizado
        DB::statement($newSchema);

        // Copiar datos con conversión
        $columnList = implode(', ', $columns);
        $convertList = implode(', ', array_map(fn($col) => "CAST({$col} AS INTEGER) as {$col}", $columns));

        // Obtener todas las columnas de la tabla original
        $allColumns = DB::select("PRAGMA table_info({$tempTable})");
        $allColumnNames = array_map(fn($col) => $col->name, $allColumns);

        // Construir SELECT con conversión
        $selectColumns = [];
        foreach ($allColumnNames as $col) {
            if (in_array($col, $columns)) {
                $selectColumns[] = "CAST({$col} AS INTEGER) as {$col}";
            } else {
                $selectColumns[] = $col;
            }
        }

        $selectList = implode(', ', $selectColumns);
        $insertList = implode(', ', $allColumnNames);

        DB::statement("
            INSERT INTO {$table} ({$insertList})
            SELECT {$selectList} FROM {$tempTable}
        ");

        // Eliminar tabla temporal
        DB::statement("DROP TABLE {$tempTable}");

        echo "✅ {$table} convertido a INTEGER\n";
    }
};
