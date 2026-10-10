<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * HALLAZGO CA-03: Ajustar el ámbito de la clave de idempotencia de las cuentas
     * y agregar payload_hash para detectar reutilización con datos diferentes.
     */
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            // 1. Eliminar la restricción única anterior (solo company_id)
            $sm = DB::connection()->getSchemaBuilder();
            $indexes = $sm->getIndexes('bills');
            
            foreach ($indexes as $index) {
                if ($index['unique'] && in_array('idempotency_key', $index['columns'])) {
                    $table->dropUnique($index['name']);
                }
            }
            
            // 2. Agregar payload_hash para validar integridad del payload
            $table->string('payload_hash', 64)->nullable()->after('idempotency_key');
            
            // 3. Crear nueva restricción única con ámbito explícito: company + branch
            $table->unique(
                ['company_id', 'branch_id', 'idempotency_key'], 
                'bills_tenant_branch_idempotency_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropUnique('bills_tenant_branch_idempotency_unique');
            $table->dropColumn('payload_hash');
            
            // Restaurar restricción anterior
            $table->unique(['company_id', 'idempotency_key'], 'bills_company_idempotency_unique');
        });
    }
};
