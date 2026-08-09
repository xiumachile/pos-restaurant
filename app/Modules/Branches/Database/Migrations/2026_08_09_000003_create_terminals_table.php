<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Multiempresa
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            // Identificación
            $table->string('code', 50);
            $table->string('name', 100);

            // Configuración (sección 9.3: terminal manda en locale)
            $table->string('locale', 10)->default('es-CL');
            $table->string('mac_address', 100)->nullable();

            // Tipos de terminal
            $table->boolean('is_kds')->default(false);
            $table->boolean('is_pos')->default(true);

            // Estado
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Unique: código único dentro de la sucursal
            $table->unique(['branch_id', 'code'], 'uk_terminal_code');

            // Índices
            $table->index(['company_id', 'branch_id', 'is_active']);
            $table->index(['branch_id', 'is_pos']);
            $table->index(['branch_id', 'is_kds']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminals');
    }
};
