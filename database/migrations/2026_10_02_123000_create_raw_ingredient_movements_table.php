<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ADR-022: Tabla de historial de movimientos de insumos.
     * 
     * Unifica los tipos de movimiento del antiguo Inventory con los
     * movimientos de consumo de recetas. Cada movimiento registra:
     * - Tipo (compra, producción, consumo, merma, ajuste)
     * - Cantidad en unidad base SI
     * - Balance después del movimiento (trazabilidad completa)
     * - Referencia polimórfica (order, purchase, etc.)
     * - Usuario y razón (auditoría)
     */
    public function up(): void
    {
        Schema::create('raw_ingredient_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('raw_ingredient_id')->constrained('raw_ingredients')->cascadeOnDelete();
            
            // Tipo de movimiento (enum string)
            $table->string('type', 30)->index();
            
            // Cantidad en unidad base SI (positiva para entrada, negativa para salida)
            $table->decimal('quantity_base', 14, 4);
            
            // Balance después del movimiento (trazabilidad completa)
            $table->decimal('balance_after', 14, 4);
            
            // Referencia polimórfica (order, purchase, manual_adjustment, etc.)
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            
            // Auditoría: quién y por qué
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            
            $table->timestamps();

            // Índices para queries comunes
            $table->index(['company_id', 'branch_id', 'raw_ingredient_id'], 'idx_movements_ingredient');
            $table->index(['reference_type', 'reference_id'], 'idx_movements_reference');
            $table->index(['company_id', 'created_at'], 'idx_movements_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_ingredient_movements');
    }
};
