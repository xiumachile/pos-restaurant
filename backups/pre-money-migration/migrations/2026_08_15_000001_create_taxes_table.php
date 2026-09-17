<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            
            // Identificación del impuesto
            $table->string('name', 100); // "IVA 19%", "Exento", "Tasa Reducida"
            $table->string('code', 20)->nullable(); // Código SII: "IVA", "EXENTO"
            
            // Tipo y valor
            $table->string('type', 20); // percent, fixed, exempt
            $table->decimal('rate', 10, 4)->default(0); // 19.00 para IVA, 500 para fijo
            
            // Configuración
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            
            // Metadata
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_default']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxes');
    }
};
