<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hallazgo I-01: Agregar versionado de recetas e idempotencia a movimientos.
 * 
 * Problema:
 * - No hay forma de auditar qué versión de receta se usó en una venta
 * - No hay idempotencia, riesgo de duplicación por reintento
 * - No hay order_item_uuid para rastrear qué plato consumió qué ingrediente
 * 
 * Solución:
 * - Agregar version a product_recipes (auto-incremental al actualizar)
 * - Agregar idempotency_key a raw_ingredient_movements (único)
 * - Agregar order_item_uuid para trazabilidad por plato
 * - Agregar recipe_version para auditoría
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar version a product_recipes
        Schema::table('product_recipes', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('total_recipe_cost');
            $table->index(['company_id', 'version']);
        });

        // 2. Agregar campos de idempotencia y trazabilidad a raw_ingredient_movements
        Schema::table('raw_ingredient_movements', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable()->unique()->after('uuid');
            $table->uuid('order_item_uuid')->nullable()->after('reference_id');
            $table->unsignedInteger('recipe_version')->nullable()->after('order_item_uuid');
            
            $table->index(['company_id', 'branch_id', 'idempotency_key'], 'idx_movements_idempotency');
            $table->index(['company_id', 'branch_id', 'order_item_uuid'], 'idx_movements_order_item');
        });
    }

    public function down(): void
    {
        Schema::table('raw_ingredient_movements', function (Blueprint $table) {
            $table->dropIndex('idx_movements_idempotency');
            $table->dropIndex('idx_movements_order_item');
            
            $table->dropColumn(['idempotency_key', 'order_item_uuid', 'recipe_version']);
        });

        Schema::table('product_recipes', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
