<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tabla restaurant_tables
        // Eliminamos la restricción antigua que solo permitía 0, 90, 180, 270
        DB::statement('ALTER TABLE restaurant_tables DROP CONSTRAINT IF EXISTS chk_table_rotation');
        
        // Creamos la nueva restricción que permite cualquier ángulo de 0 a 359 grados
        DB::statement('ALTER TABLE restaurant_tables ADD CONSTRAINT chk_table_rotation CHECK (rotation >= 0 AND rotation < 360)');

        // 2. Tabla floor_plan_objects (por consistencia y para evitar futuros errores)
        DB::statement('ALTER TABLE floor_plan_objects DROP CONSTRAINT IF EXISTS chk_fpo_rotation');
        DB::statement('ALTER TABLE floor_plan_objects ADD CONSTRAINT chk_fpo_rotation CHECK (rotation >= 0 AND rotation < 360)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertimos a la restricción original por si necesitamos hacer rollback
        DB::statement('ALTER TABLE restaurant_tables DROP CONSTRAINT IF EXISTS chk_table_rotation');
        DB::statement("ALTER TABLE restaurant_tables ADD CONSTRAINT chk_table_rotation CHECK (rotation IN (0, 90, 180, 270))");

        DB::statement('ALTER TABLE floor_plan_objects DROP CONSTRAINT IF EXISTS chk_fpo_rotation');
        DB::statement("ALTER TABLE floor_plan_objects ADD CONSTRAINT chk_fpo_rotation CHECK (rotation IN (0, 90, 180, 270))");
    }
};
