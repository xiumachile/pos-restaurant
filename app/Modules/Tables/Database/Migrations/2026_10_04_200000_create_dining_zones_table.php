<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4.1 - Floor Plan: tabla de zonas del restaurante.
 *
 * Reemplaza evolutivamente al antiguo campo area_code de restaurant_tables.
 * Permite gestionar zonas (comedor, terraza, bar, VIP) con:
 * - Nombre traducible (es/zh)
 * - Color distintivo para el floor plan
 * - Nivel de piso (preparado para restaurantes multi-nivel)
 * - Ordenamiento visual
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_zones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();

            // Código único por sucursal (ej: MAIN, TERRAZA, BAR, VIP)
            $table->string('code', 50);

            // Nombre traducible (es/zh)
            $table->jsonb('name_translations');

            // Color hexadecimal para visualización (ej: #f97316)
            $table->string('color', 10)->default('#64748b');

            // Nivel de piso (0 = planta baja, 1 = segundo piso, etc.)
            $table->integer('floor_level')->default(0);

            // Orden de visualización
            $table->integer('sort_order')->default(0);

            // Habilitada/deshabilitada (no elimina mesas de la zona)
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Sync fields (patrón del proyecto)
            $table->string('sync_status', 20)->default('pending');
            $table->integer('version')->default(1);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('offline_id', 64)->nullable();

            // Índices
            $table->unique(['branch_id', 'code'], 'uk_dining_zone_branch_code');
            $table->index(['company_id', 'branch_id', 'is_active'], 'idx_zones_tenant_active');
            $table->index('name_translations')->using('gin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_zones');
    }
};
