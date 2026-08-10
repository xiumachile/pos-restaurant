<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eliminar el constraint UNIQUE solo si existe (idempotente)
        DB::statement("
            ALTER TABLE restaurant_tables
            DROP CONSTRAINT IF EXISTS uk_area_code
        ");

        // Agregar índice normal solo si no existe
        $indexExists = DB::select("
            SELECT indexname FROM pg_indexes
            WHERE tablename = 'restaurant_tables'
            AND indexname = 'idx_tables_branch_area'
        ");

        if (empty($indexExists)) {
            Schema::table('restaurant_tables', function (Blueprint $table) {
                $table->index(['branch_id', 'area_code'], 'idx_tables_branch_area');
            });
        }
    }

    public function down(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropIndex('idx_tables_branch_area');
            $table->unique(['branch_id', 'area_code'], 'uk_area_code');
        });
    }
};
