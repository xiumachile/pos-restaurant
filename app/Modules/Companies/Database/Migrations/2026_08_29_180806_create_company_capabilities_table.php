<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_capabilities', function (Blueprint $table) {
            $table->id();
            
            // Relación con company
            $table->foreignId('company_id')
                ->constrained('companies')
                ->onDelete('cascade');
            
            // Clave del capability (ej: 'can_split_bills')
            $table->string('capability_key', 50);
            
            // Estado
            $table->boolean('is_enabled')->default(true);
            
            // Configuración específica del capability (JSONB)
            $table->jsonb('settings')->default('{}');
            
            $table->timestamps();
            
            // Unicidad: una capability por empresa
            $table->unique(['company_id', 'capability_key']);
            
            // Índices para performance
            $table->index('company_id');
            $table->index('capability_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_capabilities');
    }
};
