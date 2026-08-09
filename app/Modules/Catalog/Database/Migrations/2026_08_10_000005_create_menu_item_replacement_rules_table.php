<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_replacement_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Multiempresa
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // branch_id NULL = regla global de empresa
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->cascadeOnDelete();

            // Combo al que aplica la regla
            $table->foreignId('menu_item_id')
                ->constrained('menu_items')
                ->cascadeOnDelete();

            // Producto original a reemplazar (NULL = aplica a todos)
            $table->foreignId('target_product_id')
                ->nullable()
                ->constrained('products')
                ->cascadeOnDelete();

            // Tipo de regla: any_product, allowed_product, allowed_category
            $table->string('rule_type', 50);

            // Producto específico permitido (si rule_type = allowed_product)
            $table->foreignId('allowed_product_id')
                ->nullable()
                ->constrained('products')
                ->cascadeOnDelete();

            // Categoría permitida (si rule_type = allowed_category)
            $table->foreignId('allowed_category_id')
                ->nullable()
                ->constrained('categories')
                ->cascadeOnDelete();

            // Recargo máximo permitido (NULL = sin límite)
            $table->decimal('max_price_delta', 12, 2)->nullable();

            // Si requiere PIN de encargado/admin
            $table->boolean('requires_authorization')->default(false);

            // Prioridad (menor = más prioritaria)
            $table->integer('priority')->default(1);

            // Estado
            $table->boolean('is_active')->default(true);

            // Descripción i18n (para mostrar en backoffice)
            $table->jsonb('description_translations')->default('{}');

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['menu_item_id', 'is_active']);
            $table->index(['company_id', 'branch_id']);
            $table->index(['company_id', 'menu_item_id', 'target_product_id']);
        });

        // Constraint: rule_type válido
        DB::statement("
            ALTER TABLE menu_item_replacement_rules
            ADD CONSTRAINT chk_replacement_rule_type
            CHECK (rule_type IN ('any_product', 'allowed_product', 'allowed_category'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_replacement_rules');
    }
};
