<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el campo fulfillment_channel a la tabla orders con backfill automático.
 *
 * CAMPO AGREGADO:
 * - fulfillment_channel (string, nullable, default 'onsite')
 *   Representa CÓMO se entrega el pedido (onsite, pickup, delivery).
 *   Es distinto de `type` que representa QUÉ TIPO de pedido es.
 *
 * BACKFILL (pedidos existentes):
 * - type='dine_in'   → fulfillment_channel='onsite'
 * - type='takeout'   → fulfillment_channel='pickup'
 * - type='delivery'  → fulfillment_channel='delivery'
 *
 * RELACIÓN type ↔ fulfillment:
 * - dine_in   → onsite por defecto (puede sobreescribirse a pickup para "comer en barra")
 * - takeout   → pickup por defecto (típicamente no se sobreescribe)
 * - delivery  → delivery por defecto (típicamente no se sobreescribe)
 *
 * Este campo permite distinguir casos edge como:
 * - dine_in pero cliente pide llevar (dine_in + pickup) → raro pero válido
 * - takeout pero cliente decide comer en el local (takeout + onsite) → poco común
 * - delivery directo (delivery + delivery) → caso canónico
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment_channel')->nullable()->after('type');
        });

        // Backfill basado en el tipo de pedido existente
        DB::table('orders')->where('type', 'dine_in')->update(['fulfillment_channel' => 'onsite']);
        DB::table('orders')->where('type', 'takeout')->update(['fulfillment_channel' => 'pickup']);
        DB::table('orders')->where('type', 'delivery')->update(['fulfillment_channel' => 'delivery']);

        // Hacer NOT NULL después del backfill
        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment_channel')->default('onsite')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('fulfillment_channel');
        });
    }
};
