<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cash_registers')) {
            Schema::create('cash_registers', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                
                // Identificación de la caja
                $table->string('name', 100); // "Caja 1", "Caja Principal", "Caja Rápida"
                $table->string('code', 50); // "CAJA-01", "MAIN-01"
                $table->text('description')->nullable();
                
                // Configuración
                $table->decimal('opening_amount_default', 14, 2)->default(50000); // Monto inicial por defecto
                $table->decimal('max_amount', 14, 2)->default(500000); // Máximo antes de retiro obligatorio
                $table->boolean('requires_dual_control')->default(false); // Requiere supervisor para cerrar
                
                // Hardware
                $table->string('printer_id', 100)->nullable(); // ID de impresora de tickets
                $table->string('drawer_serial', 100)->nullable(); // Número de serie del cajón
                
                // Estado
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'branch_id', 'code']);
                $table->index(['company_id', 'branch_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};
