<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla de clientes (CRM básico).
 * 
 * IDEMPOTENTE: Usa IF NOT EXISTS para evitar conflictos con RefreshDatabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Verificar si la tabla ya existe (idempotente)
        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                
                // Multi-tenant
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                
                // Datos principales
                $table->string('phone', 30);
                $table->string('name', 200);
                $table->string('email', 200)->nullable();
                
                // Datos de delivery
                $table->string('address', 500)->nullable();
                $table->string('commune', 100)->nullable();
                $table->string('address_reference', 500)->nullable();
                
                // Notas internas
                $table->text('notes')->nullable();
                
                // Sincronización
                $table->string('sync_status', 20)->default('synced');
                $table->integer('version')->default(1);
                $table->timestamp('last_synced_at')->nullable();
                $table->string('offline_id')->nullable();
                
                $table->timestamps();
                $table->softDeletes();
                
                // Índices
                $table->unique(['company_id', 'phone']);
                $table->index(['company_id', 'name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
