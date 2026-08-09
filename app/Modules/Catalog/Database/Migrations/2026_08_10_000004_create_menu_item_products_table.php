<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Combo al que pertenece
            $table->foreignId('menu_item_id')
                ->constrained('menu_items')
                ->cascadeOnDelete();

            // Producto incluido en el combo
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            // Cantidad del producto en el combo (ej: 2 bebidas)
            $table->integer('quantity')->default(1);

            // Si el producto puede ser sustituido por el cliente
            $table->boolean('is_substitutable')->default(true);

            $table->timestamps();

            // Un producto aparece una vez por combo (cantidad maneja repetición)
            $table->unique(['menu_item_id', 'product_id'], 'uk_menu_item_product_composition');

            // Índices
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_products');
    }
};
