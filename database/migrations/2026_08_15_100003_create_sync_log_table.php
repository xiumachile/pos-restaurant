<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_log', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            
            // Información del sync
            $table->string('sync_session_id', 64); // UUID del lote de sync
            $table->string('direction', 20); // push (client→server) o pull (server→client)
            
            // Entidad
            $table->string('entity_type', 150);
            $table->unsignedBigInteger('entity_id');
            $table->uuid('entity_uuid')->nullable();
            $table->string('action', 20);
            
            // Resultado
            $table->string('result', 20); // success, conflict, error
            $table->json('conflict_data')->nullable(); // Datos del conflicto si aplica
            $table->text('error_message')->nullable();
            $table->integer('duration_ms')->nullable(); // Tiempo de procesamiento
            
            // Metadata
            $table->json('metadata')->nullable();
            
            $table->timestamp('synced_at')->useCurrent();

            $table->index(['company_id', 'branch_id']);
            $table->index('sync_session_id');
            $table->index(['entity_type', 'entity_id']);
            $table->index('synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_log');
    }
};
