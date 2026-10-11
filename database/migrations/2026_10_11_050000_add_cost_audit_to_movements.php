<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hallazgo I-03: Registro del costo unitario y total en el momento de la transacción.
 * 
 * Problema: Cambios futuros en el precio de compra alteraban retroactivamente el margen de ventas pasadas.
 * Solución: Congelar el 'cost_per_base_unit' y el 'total_cost' en cada movimiento (especialmente consumo).
 * Método de valoración: Costo Promedio Ponderado (WAC).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_ingredient_movements', function (Blueprint $table) {
            $table->decimal('cost_per_base_unit_at_transaction', 14, 4)->nullable()->after('original_unit');
            $table->decimal('total_cost_at_transaction', 14, 2)->nullable()->after('cost_per_base_unit_at_transaction');
        });
    }

    public function down(): void
    {
        Schema::table('raw_ingredient_movements', function (Blueprint $table) {
            $table->dropColumn(['cost_per_base_unit_at_transaction', 'total_cost_at_transaction']);
        });
    }
};
