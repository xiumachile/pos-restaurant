<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MIGRACIÓN P0-2: Scope payments.idempotency_key a tenant
 * 
 * Problema: `payments.idempotency_key` tenía unique global,
 * impidiendo que dos tenants usaran la misma key legítimamente.
 * 
 * Fix: Reemplazar unique global por unique compuesto
 * (company_id, branch_id, idempotency_key).
 * 
 * Alcance (Opción X): Dos branches del mismo tenant pueden
 * reutilizar keys (ej: terminal A y B procesando el mismo pago offline).
 * Dos tenants NO pueden colisionar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Eliminar unique global
            $table->dropUnique(['idempotency_key']);
            
            // Crear unique compuesto por tenant
            $table->unique(
                ['company_id', 'branch_id', 'idempotency_key'],
                'payments_tenant_idempotency_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_tenant_idempotency_unique');
            $table->unique('idempotency_key'); // Restaura unique global
        });
    }
};
