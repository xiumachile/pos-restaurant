<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printer_station_mappings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('printer_id')->constrained('printers')->cascadeOnDelete();
            
            // Categoría de productos que van a esta impresora
            $table->foreignId('category_id')->nullable()->constrained('categories')->cascadeOnDelete();
            
            // O alternativamente, palabras clave en el nombre del producto
            $table->jsonb('product_keywords')->nullable(); // ["wok", "salteado", "frito"]
            
            // Prioridad (si hay múltiples impresoras para la misma categoría)
            $table->integer('priority')->default(1);
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'category_id']);
            $table->index(['company_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printer_station_mappings');
    }
};
