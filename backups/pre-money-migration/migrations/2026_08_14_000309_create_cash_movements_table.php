<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cash_movements')) {
            Schema::create('cash_movements', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('cash_session_id')->constrained('cash_sessions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                
                // Tipo de movimiento
                $table->string('type', 30); // withdrawal, deposit, adjustment
                $table->decimal('amount', 14, 2); // Siempre positivo
                
                // Razón y referencia
                $table->string('reason', 200); // "Exceso en caja", "Falta cambio", "Error de conteo"
                $table->text('notes')->nullable();
                $table->string('reference_type', 50)->nullable(); // order, payment, manual
                $table->string('reference_id', 100)->nullable(); // UUID relacionado
                
                // Autorización (para montos grandes)
                $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('authorized_at')->nullable();
                
                // Balance después del movimiento
                $table->decimal('balance_after', 14, 2);
                
                $table->timestamps();
                $table->softDeletes();

                $table->index(['cash_session_id', 'type']);
                $table->index(['company_id', 'branch_id', 'created_at']);
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
