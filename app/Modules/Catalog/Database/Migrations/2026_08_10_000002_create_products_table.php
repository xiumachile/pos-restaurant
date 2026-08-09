<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Multiempresa
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // branch_id NULL = producto global de empresa
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->cascadeOnDelete();

            // Categoría (nullable para productos sin categorizar)
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            // Identificación
            $table->string('sku', 100)->nullable();

            // i18n JSONB
            $table->jsonb('name_translations');
            $table->jsonb('description_translations')->default('{}');

            // Precios e impuestos
            $table->decimal('base_price', 12, 2)->default(0.00);
            $table->decimal('tax_rate', 5, 2)->default(19.00);

            // Tipo: producto simple o combo
            $table->boolean('is_combo')->default(false);

            // Zona de cocina (se usará en Fase 6 - Kitchen)
            $table->uuid('kitchen_zone_id')->nullable();

            // Estado
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['company_id', 'category_id']);
            $table->index(['company_id', 'is_combo', 'is_active']);
            $table->index(['company_id', 'branch_id']);

            // SKU único por empresa
            $table->unique(['company_id', 'sku'], 'uk_product_sku');
        });

        // Índices GIN para búsquedas en traducciones
        DB::statement('CREATE INDEX idx_products_name_translations ON products USING gin (name_translations)');
        DB::statement('CREATE INDEX idx_products_description_translations ON products USING gin (description_translations)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
