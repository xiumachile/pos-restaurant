<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4.1 Rediseño - Sección 8.2 de la spec
 * Entidad FloorPlanObject: cada elemento gráfico del plano
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floor_plan_objects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('floor_plan_id')->constrained('floor_plans')->cascadeOnDelete();

            // Tipo de objeto: table, chair, plant, wall, door, window, etc.
            $table->string('object_type', 50);

            // Vinculación operativa (Sección 8.2 - object_key)
            // Para mesas: UUID de restaurant_tables.uuid
            // Para otros objetos: null o referencia según tipo
            $table->uuid('object_key')->nullable();

            // Posición y dimensiones (unidades del plano)
            $table->integer('x')->default(0);
            $table->integer('y')->default(0);
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('rotation')->default(0);

            // Capas (z-index)
            $table->integer('z_index')->default(0);

            // Propiedades específicas según tipo
            $table->jsonb('properties')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['floor_plan_id', 'object_type'], 'idx_fp_objects_plan_type');
            $table->index('object_key', 'idx_fp_objects_key');
            $table->unique(['floor_plan_id', 'object_key'], 'uk_fp_objects_plan_key')
                  ->where('object_key IS NOT NULL'); // índice único parcial
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floor_plan_objects');
    }
};
