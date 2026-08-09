<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Multiempresa
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // branch_id NULL = categoría global de empresa
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->cascadeOnDelete();

            // i18n JSONB: {"es": "Bebidas", "zh": "饮料"}
            $table->jsonb('name_translations');

            // Ordenamiento y estado
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'branch_id']);
        });

        // Índice GIN para búsquedas en traducciones JSONB
        DB::statement('CREATE INDEX idx_categories_name_translations ON categories USING gin (name_translations)');
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
