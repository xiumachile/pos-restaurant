<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Multiempresa
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // branch_id NULL = combo global de empresa
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->cascadeOnDelete();

            // Producto tipo combo (products.is_combo = true)
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            // Precio base del combo (suma de productos - descuento)
            $table->decimal('base_price', 12, 2);

            // Descuento aplicado al combo
            $table->decimal('discount_amount', 12, 2)->default(0.00);

            // Estado
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Un combo por producto dentro de empresa/sucursal
            $table->unique(['company_id', 'branch_id', 'product_id'], 'uk_menu_item_product');

            // Índices
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
