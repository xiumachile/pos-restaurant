<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hallazgo I-02: Registro de unidades originales para auditoría de conversiones.
 * 
 * Problema: No quedaba registro de si el usuario ingresó "1 kg" o "1000 g".
 * Solución: Almacenar original_quantity y original_unit junto con el quantity_base calculado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipe_items', function (Blueprint $table) {
            $table->decimal('original_quantity', 14, 4)->nullable()->after('quantity_base_unit');
            $table->string('original_unit', 20)->nullable()->after('original_quantity');
        });

        Schema::table('raw_ingredient_movements', function (Blueprint $table) {
            $table->decimal('original_quantity', 14, 4)->nullable()->after('quantity_base');
            $table->string('original_unit', 20)->nullable()->after('original_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('raw_ingredient_movements', function (Blueprint $table) {
            $table->dropColumn(['original_quantity', 'original_unit']);
        });

        Schema::table('recipe_items', function (Blueprint $table) {
            $table->dropColumn(['original_quantity', 'original_unit']);
        });
    }
};
