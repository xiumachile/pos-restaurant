<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4.1 Rediseño - Sección 8.1 de la spec
 * Entidad FloorPlan: representa un salón del restaurante
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floor_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();

            // Identificación
            $table->string('name', 100);
            $table->string('slug', 100)->nullable(); // ej: salon-principal, terraza

            // Dimensiones y escala (Sección 4.3)
            $table->integer('width')->default(1200);   // ancho lógico (unidades)
            $table->integer('height')->default(1800);  // alto lógico (unidades)
            $table->decimal('scale', 5, 2)->default(100.00); // 1 metro = 100 unidades

            // Configuración visual
            $table->jsonb('background')->nullable(); // color, imagen, etc.
            $table->jsonb('settings')->nullable();   // grid, snap, etc.

            // Control de versiones (Sección 9)
            $table->integer('version')->default(1);
            $table->string('status', 20)->default('draft'); // draft, published, archived
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Sync fields
            $table->string('sync_status', 20)->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->string('offline_id', 64)->nullable();

            // Índices
            $table->index(['branch_id', 'status'], 'idx_floor_plans_branch_status');
            $table->unique(['branch_id', 'slug'], 'uk_floor_plans_branch_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floor_plans');
    }
};
