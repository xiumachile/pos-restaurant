<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_queue', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            
            // Entidad afectada
            $table->string('entity_type', 150); // FQCN del modelo
            $table->unsignedBigInteger('entity_id');
            $table->uuid('entity_uuid')->nullable();
            
            // Acción
            $table->string('action', 20); // create, update, delete
            $table->json('payload'); // Datos del cambio
            $table->unsignedInteger('version')->default(1);
            
            // Control de reintentos
            $table->unsignedInteger('attempts')->default(0);
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
            $table->text('error_message')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'branch_id', 'status']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['status', 'next_attempt_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_queue');
    }
};
