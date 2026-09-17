<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cash_counts')) {
            Schema::create('cash_counts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('cash_session_id')->constrained('cash_sessions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                
                // Tipo de conteo
                $table->string('type', 30); // opening, closing, partial, audit
                $table->string('reason', 200)->nullable(); // "Cierre de turno", "Arqueo parcial"
                
                // Montos calculados
                $table->decimal('expected_amount', 14, 2); // Lo que debería haber
                $table->decimal('counted_amount', 14, 2); // Lo que se contó físicamente
                $table->decimal('difference', 14, 2); // counted - expected
                
                // Conteo detallado por denominación (JSON)
                // Estructura: { "bills": { "20000": 5, "10000": 3, ... }, "coins": { "500": 10, ... } }
                $table->jsonb('denominations')->nullable();
                
                // Método de pago desglosado
                $table->decimal('cash_amount', 14, 2)->default(0); // Efectivo
                $table->decimal('card_amount', 14, 2)->default(0); // Tarjetas
                $table->decimal('transfer_amount', 14, 2)->default(0); // Transferencias
                $table->decimal('other_amount', 14, 2)->default(0); // Otros
                
                // Observaciones
                $table->text('notes')->nullable();
                $table->boolean('has_discrepancy')->default(false);
                $table->text('discrepancy_explanation')->nullable();
                
                // Supervisión (si hay discrepancia grande)
                $table->foreignId('supervised_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('supervised_at')->nullable();
                
                $table->timestamps();
                $table->softDeletes();

                $table->index(['cash_session_id', 'type']);
                $table->index(['company_id', 'branch_id', 'created_at']);
                $table->index(['has_discrepancy', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_counts');
    }
};
