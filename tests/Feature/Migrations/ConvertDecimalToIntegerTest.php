<?php

namespace Tests\Feature\Migrations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConvertDecimalToIntegerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear tabla de prueba con ambos escenarios
        Schema::create('test_money_conversion', function ($table) {
            $table->id();
            // Escenario 1: DECIMAL NOT NULL con DEFAULT
            $table->decimal('amount_not_null', 14, 2)->default(0);
            // Escenario 2: DECIMAL NULL (nullable)
            $table->decimal('amount_nullable', 14, 2)->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_money_conversion');
        parent::tearDown();
    }

    #[Test]
    public function it_converts_decimal_not_null_to_integer_not_null_preserving_constraints(): void
    {
        // 1. Verificar estado inicial
        $initial = $this->getColumnInfo('test_money_conversion', 'amount_not_null');
        $this->assertEquals('numeric', $initial->data_type);
        $this->assertEquals('NO', $initial->is_nullable, 'Precondición fallida: la columna debería ser NOT NULL');

        // 2. Ejecutar lógica de conversión (réplica de la migración corregida)
        $this->convertColumn('test_money_conversion', 'amount_not_null');

        // 3. Verificar estado final
        $final = $this->getColumnInfo('test_money_conversion', 'amount_not_null');
        $this->assertEquals('integer', $final->data_type, 'El tipo de dato no se convirtió a integer');
        $this->assertEquals('NO', $final->is_nullable, 'P1-002 FALLÓ: La columna NOT NULL perdió su restricción NOT NULL');
        $this->assertNotNull($final->column_default, 'El DEFAULT se perdió durante la conversión');
    }

    #[Test]
    public function it_converts_decimal_nullable_to_integer_nullable_preserving_constraints(): void
    {
        // 1. Verificar estado inicial
        $initial = $this->getColumnInfo('test_money_conversion', 'amount_nullable');
        $this->assertEquals('numeric', $initial->data_type);
        $this->assertEquals('YES', $initial->is_nullable, 'Precondición fallida: la columna debería ser NULL');

        // 2. Ejecutar lógica de conversión
        $this->convertColumn('test_money_conversion', 'amount_nullable');

        // 3. Verificar estado final
        $final = $this->getColumnInfo('test_money_conversion', 'amount_nullable');
        $this->assertEquals('integer', $final->data_type, 'El tipo de dato no se convirtió a integer');
        $this->assertEquals('YES', $final->is_nullable, 'P1-002 FALLÓ: La columna NULL se convirtió incorrectamente a NOT NULL');
    }

    private function getColumnInfo(string $table, string $column): object
    {
        return DB::selectOne("
            SELECT data_type, is_nullable, column_default
            FROM information_schema.columns 
            WHERE table_name = ? AND column_name = ?
        ", [$table, $column]);
    }

    private function convertColumn(string $table, string $column): void
    {
        $originalColumn = $this->getColumnInfo($table, $column);
        $wasNullable = $originalColumn->is_nullable === 'YES';
        $originalDefault = $originalColumn->column_default;

        if ($originalDefault !== null) {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP DEFAULT");
        }

        if (!$wasNullable) {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP NOT NULL");
        }

        DB::statement("
            ALTER TABLE {$table} 
            ALTER COLUMN {$column} TYPE INTEGER 
            USING (CASE WHEN {$column} IS NULL THEN 0 ELSE ROUND({$column})::INTEGER END)
        ");

        if (!$wasNullable) {
            DB::statement("UPDATE {$table} SET {$column} = 0 WHERE {$column} IS NULL");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET NOT NULL");
        }

        if ($originalDefault !== null) {
            $cleanDefault = is_numeric($originalDefault) ? (int)round((float)$originalDefault) : 0;
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT {$cleanDefault}");
        }
    }
}
