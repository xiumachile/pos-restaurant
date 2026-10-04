<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4.1 - Floor Plan: agrega campos de posición espacial a las mesas.
 *
 * Estrategia evolutiva:
 * - zone_id es NULLABLE (preserva compatibilidad con area_code)
 * - Campos de posición con defaults (0,0,0,'square') para no romper datos existentes
 * - area_code se mantiene como campo legacy
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            // Relación con dining_zones (nullable durante transición)
            $table->foreignId('zone_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('dining_zones')
                ->nullOnDelete();

            // Posición en el canvas del floor plan (pixeles)
            $table->integer('position_x')->default(0)->after('capacity');
            $table->integer('position_y')->default(0)->after('position_x');

            // Rotación en grados (0, 90, 180, 270)
            $table->integer('rotation')->default(0)->after('position_y');

            // Forma visual de la mesa
            $table->string('shape', 20)->default('square')->after('rotation');

            // Ancho y alto custom (para mesas rectangulares)
            $table->integer('width')->nullable()->after('shape');
            $table->integer('height')->nullable()->after('width');

            // Índice para queries de floor plan
            $table->index(['zone_id', 'status'], 'idx_tables_zone_status');
            $table->index(['branch_id', 'zone_id'], 'idx_tables_branch_zone');
        });

        // Check constraint para shape
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE restaurant_tables ADD CONSTRAINT chk_table_shape CHECK (shape IN ('square', 'round', 'rectangle'))"
        );

        // Check constraint para rotation (solo múltiplos de 90)
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE restaurant_tables ADD CONSTRAINT chk_table_rotation CHECK (rotation IN (0, 90, 180, 270))"
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE restaurant_tables DROP CONSTRAINT IF EXISTS chk_table_rotation"
        );
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE restaurant_tables DROP CONSTRAINT IF EXISTS chk_table_shape"
        );

        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropIndex('idx_tables_zone_status');
            $table->dropIndex('idx_tables_branch_zone');
            $table->dropConstrainedForeignId('zone_id');
            $table->dropColumn(['position_x', 'position_y', 'rotation', 'shape', 'width', 'height']);
        });
    }
};
