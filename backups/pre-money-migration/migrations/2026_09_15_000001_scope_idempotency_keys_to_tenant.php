<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * ADR-015: Idempotencia scoping a tenant
     * - Agrega company_id/branch_id para scope de tenant
     * - Crea UNIQUE constraint (company_id, key) para prevenir colisiones cross-tenant
     * - Las columnas son NULLABLE para compatibilidad con tests legacy
     */
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            $table->unsignedBigInteger('branch_id')->nullable()->after('company_id');
            
            // Índice para scope de tenant
            $table->index(['company_id', 'branch_id']);
        });

        // Reemplazar UNIQUE global por UNIQUE scoped
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->unique(['company_id', 'key'], 'idempotency_company_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropUnique('idempotency_company_key_unique');
            $table->unique(['key']);
        });

        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'branch_id']);
            $table->dropColumn(['company_id', 'branch_id']);
        });
    }
};
