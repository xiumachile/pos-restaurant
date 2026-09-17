<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
/**
Migración P0: Agregar campos del modelo chileno (ADR-011) a tabla orders
Problema: Backend usa modelo NETO (subtotal + tax = total)
Solución: Agregar campos para modelo BRUTO (gross incluye IVA)
Campos agregados:
subtotal_gross: Suma de items (IVA incluido)
net_amount: Gross / 1.19
tip_amount: Propina (separada del IVA)
amount_due: Total a cobrar (gross + tip - discount)
*/
return new class extends Migration
{
public function up(): void
{
Schema::table('orders', function (Blueprint $table) {
$table->decimal('subtotal_gross', 14, 2)->nullable()->after('subtotal');
$table->decimal('net_amount', 14, 2)->nullable()->after('subtotal_gross');
$table->decimal('tip_amount', 14, 2)->default(0)->after('tax_amount');
$table->decimal('amount_due', 14, 2)->nullable()->after('total');
});
// Backfill de datos existentes (79 orders con modelo NETO)
DB::statement("
UPDATE orders SET
subtotal_gross = total,
net_amount = ROUND(total / 1.19, 2),
tax_amount = total - ROUND(total / 1.19, 2),
tip_amount = 0,
amount_due = total
WHERE subtotal_gross IS NULL
");
// Hacer campos NOT NULL después del backfill
Schema::table('orders', function (Blueprint $table) {
$table->decimal('subtotal_gross', 14, 2)->nullable(false)->default(0)->change();
$table->decimal('net_amount', 14, 2)->nullable(false)->default(0)->change();
$table->decimal('amount_due', 14, 2)->nullable(false)->default(0)->change();
});
}
public function down(): void
{
Schema::table('orders', function (Blueprint $table) {
$table->dropColumn(['subtotal_gross', 'net_amount', 'tip_amount', 'amount_due']);
});
}
};
