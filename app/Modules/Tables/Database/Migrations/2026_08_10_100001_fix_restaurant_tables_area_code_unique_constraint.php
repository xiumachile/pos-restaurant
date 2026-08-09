<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            // Eliminar el constraint UNIQUE incorrecto
            // Un área puede tener múltiples mesas
            $table->dropUnique('uk_area_code');
        });

        // Agregar un índice normal para búsquedas rápidas por área
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->index(['branch_id', 'area_code'], 'idx_tables_branch_area');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropIndex('idx_tables_branch_area');
            $table->unique(['branch_id', 'area_code'], 'uk_area_code');
        });
    }
};
