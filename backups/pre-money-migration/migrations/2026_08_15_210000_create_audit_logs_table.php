<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Contexto multi-tenant
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            
            // Quién hizo la acción
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 255)->nullable(); // Snapshot del nombre
            
            // Qué se hizo
            $table->string('action', 100); // order_cancelled, discount_applied, drawer_opened, price_changed
            $table->string('entity_type', 255); // FQCN del modelo afectado
            $table->unsignedBigInteger('entity_id');
            $table->uuid('entity_uuid')->nullable();
            
            // Datos del evento
            $table->json('payload')->nullable(); // Datos adicionales del evento
            $table->json('changes')->nullable(); // Cambios específicos (before/after)
            
            // Contexto de la acción
            $table->string('reason', 500)->nullable(); // Razón de la acción
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            
            // Metadata
            $table->timestamp('occurred_at'); // Cuándo ocurrió la acción
            $table->timestamps();

            // Índices para consultas comunes
            $table->index(['company_id', 'branch_id', 'occurred_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['action', 'occurred_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
