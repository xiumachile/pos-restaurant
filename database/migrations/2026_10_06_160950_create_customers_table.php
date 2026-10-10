<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla de clientes (CRM básico)
 * 
 * Propósito:
 * - Almacenar datos de clientes recurrentes
 * - Búsqueda rápida por teléfono (campo prioritario)
 * - Autocompletar datos en pedidos delivery
 * - Base para programa de fidelización futuro
 * 
 * Multi-tenant:
 * - customer pertenece a una company
 * - unique(company_id, phone): teléfono único por empresa
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Multi-tenant
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            
            // Datos principales (teléfono es campo prioritario de búsqueda)
            $table->string('phone', 20);
            $table->string('name', 100);
            $table->string('email', 100)->nullable();
            
            // Datos de delivery
            $table->string('address', 200)->nullable();
            $table->string('commune', 50)->nullable();  // Comuna (Chile)
            $table->string('address_reference', 200)->nullable();  // "Depto 501, frente al parque"
            
            // Notas internas del cliente
            $table->text('notes')->nullable();
            
            // Sincronización offline/online
            $table->string('sync_status', 20)->default('synced');
            $table->integer('version')->default(1);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('offline_id')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices para búsqueda rápida
            $table->unique(['company_id', 'phone']);
            $table->index(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
