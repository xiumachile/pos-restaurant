<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ADR-020: Agregar idempotency_key a bills para sincronización offline.
     * 
     * Permite idempotencia cuando el frontend offline sincroniza bills creadas localmente.
     */
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->string('idempotency_key', 36)->nullable()->after('status');
            $table->unique(['company_id', 'idempotency_key'], 'bills_company_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropUnique('bills_company_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
