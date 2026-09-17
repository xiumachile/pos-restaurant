<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campos de fulfillment para pedidos sin mesa (Fase 2 — Order Core).
 *
 * CAMPOS AGREGADOS:
 * - customer_name: Nombre del cliente (takeout, delivery)
 * - customer_phone: Teléfono del cliente (takeout, delivery)
 * - pickup_at: Hora programada de retiro (takeout)
 * - delivery_address: Dirección de entrega (delivery)
 * - delivery_notes: Notas para el delivery (delivery, nullable)
 *
 * BACKWARD COMPATIBILITY:
 * Todos los campos son nullable. Los pedidos existentes (dine_in con mesa)
 * mantienen NULL en estos campos. Los tests existentes de takeout sin
 * customer_name/customer_phone siguen funcionando porque los campos son opcionales.
 *
 * SEMÁNTICA:
 * - dine_in: usa table_id, fulfillment_fields = NULL
 * - takeout: customer_name y customer_phone son opcionales (tests existentes),
 *            pickup_at opcional, delivery_address = NULL
 * - delivery: customer_name, customer_phone y delivery_address son requeridos
 *             (validado en CreateOrderRequest), pickup_at = NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('notes');
            $table->string('customer_phone', 30)->nullable()->after('customer_name');
            $table->timestamp('pickup_at')->nullable()->after('customer_phone');
            $table->text('delivery_address')->nullable()->after('pickup_at');
            $table->text('delivery_notes')->nullable()->after('delivery_address');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'customer_name',
                'customer_phone',
                'pickup_at',
                'delivery_address',
                'delivery_notes',
            ]);
        });
    }
};
