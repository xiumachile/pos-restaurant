<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Cocinero asignado (nullable: no todos los pedidos tienen cocinero asignado)
            $table->foreignId('assigned_cook_id')
                ->nullable()
                ->after('waiter_id')
                ->constrained('users')
                ->nullOnDelete();

            // Prioridad del pedido (normal por defecto)
            $table->string('priority', 20)->default('normal')->after('type');

            // Índice para búsquedas por prioridad
            $table->index(['branch_id', 'priority', 'status'], 'idx_orders_kitchen_queue');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_kitchen_queue');
            $table->dropForeign(['assigned_cook_id']);
            $table->dropColumn(['assigned_cook_id', 'priority']);
        });
    }
};
