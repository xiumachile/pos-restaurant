<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hallazgo I-04: Campos para cálculo de disponibilidad real de stock.
 *
 * Fórmula de disponibilidad:
 *   stock_available = current_stock_base - committed_stock_base + pending_incoming_base
 *
 * - committed_stock_base: Suma de ingredientes comprometidos por pedidos CONFIRMADOS
 *   que aún no han sido servidos/cerrados (el consumo se descuenta al confirmar).
 * - pending_incoming_base: Suma de compras/movimientos positivos locales pendientes
 *   de sincronización con el servidor central.
 * - reorder_level_base: Nivel de reposición (cuando stock_available <= este valor,
 *   se dispara alerta de reposición).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_ingredients', function (Blueprint $table) {
            $table->decimal('committed_stock_base', 14, 4)->default(0)->after('current_stock_base');
            $table->decimal('pending_incoming_base', 14, 4)->default(0)->after('committed_stock_base');
            $table->decimal('reorder_level_base', 14, 4)->nullable()->after('minimum_stock_base');
        });
    }

    public function down(): void
    {
        Schema::table('raw_ingredients', function (Blueprint $table) {
            $table->dropColumn(['committed_stock_base', 'pending_incoming_base', 'reorder_level_base']);
        });
    }
};
