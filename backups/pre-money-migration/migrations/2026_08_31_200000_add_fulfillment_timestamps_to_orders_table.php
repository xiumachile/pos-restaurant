<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega timestamps específicos para flujos de fulfillment (Fase 4 — Order Core).
 *
 * CAMPOS AGREGADOS:
 * - picked_up_at:    timestamp cuando el cliente retiró el pedido (pickup flow)
 * - dispatched_at:   timestamp cuando el pedido salió a entrega (delivery flow)
 * - delivered_at:    timestamp cuando el pedido fue entregado (delivery flow)
 *
 * Estos timestamps se setean automáticamente por OrderStateMachine al transicionar
 * a los estados correspondientes: PICKED_UP, DISPATCHED, DELIVERED.
 *
 * BACKWARD COMPATIBILITY:
 * Todos los campos son nullable. Pedidos existentes (dine_in) mantienen NULL.
 * El estado SERVED tradicional sigue usando served_at (no tocado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('picked_up_at')->nullable()->after('served_at');
            $table->timestamp('dispatched_at')->nullable()->after('picked_up_at');
            $table->timestamp('delivered_at')->nullable()->after('dispatched_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['picked_up_at', 'dispatched_at', 'delivered_at']);
        });
    }
};
