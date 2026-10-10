<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // HALLAZGO 13: Garantía de idempotencia a nivel de base de datos
        // Eliminamos cualquier índice único existente en idempotency_key
        Schema::table('payments', function (Blueprint $table) {
            $sm = DB::connection()->getSchemaBuilder();
            $indexes = $sm->getIndexes('payments');
            
            foreach ($indexes as $index) {
                // Si el índice es único y contiene idempotency_key, lo eliminamos
                if ($index['unique'] && in_array('idempotency_key', $index['columns'])) {
                    $table->dropUnique($index['name']);
                }
            }
            
            // Agregamos la restricción única compuesta correcta para multi-tenant
            $table->unique(['company_id', 'branch_id', 'idempotency_key'], 'payments_tenant_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_tenant_idempotency_unique');
            $table->unique('idempotency_key');
        });
    }
};
