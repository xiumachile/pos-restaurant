<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Vincular orders con customers + fuente del pedido
 * 
 * Cambios:
 * - customer_id: FK a customers (nullable para pedidos sin cliente identificado)
 * - source: origen del pedido ('manual', 'rappi', 'uber_eats', 'pedidos_ya', 'didifood')
 * - platform_order_code: código del pedido en la plataforma externa
 * 
 * Reglas de dominio:
 * - source='manual' → requiere customer_id (o datos completos de cliente)
 * - source='rappi|uber_eats|pedidos_ya|didifood' → requiere platform_order_code
 *   (datos de cliente son opcionales porque la app ya los tiene)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // FK a customers
            $table->foreignId('customer_id')
                ->nullable()
                ->after('cashier_id')
                ->constrained()
                ->nullOnDelete();
            
            // Fuente del pedido: manual (teléfono/mostrador) o plataforma
            $table->string('source', 20)
                ->default('manual')
                ->after('fulfillment_channel');
            
            // Código del pedido en plataforma externa (Rappi, Uber Eats, etc.)
            $table->string('platform_order_code', 100)
                ->nullable()
                ->after('source');
            
            // Índices
            $table->index('customer_id');
            $table->index('source');
            $table->index(['company_id', 'platform_order_code']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['source']);
            $table->dropIndex(['company_id', 'platform_order_code']);
            $table->dropColumn(['customer_id', 'source', 'platform_order_code']);
        });
    }
};
