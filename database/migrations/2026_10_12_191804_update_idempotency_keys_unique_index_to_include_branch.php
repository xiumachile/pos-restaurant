<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P1 Fix: Alinear el alcance de idempotency_keys con payments.
     * 
     * Problema: El índice único era solo (company_id, key), permitiendo
     * conflictos o replays incorrectos entre sucursales de la misma empresa.
     * 
     * Solución: Cambiar a (company_id, branch_id, key) para aislamiento total.
     */
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            // Eliminar el índice antiguo que no incluía branch_id
            $table->dropUnique('idempotency_company_key_unique');
            
            // Crear el nuevo índice con alcance completo de tenant
            $table->unique(['company_id', 'branch_id', 'key'], 'idempotency_tenant_branch_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropUnique('idempotency_tenant_branch_key_unique');
            $table->unique(['company_id', 'key'], 'idempotency_company_key_unique');
        });
    }
};
