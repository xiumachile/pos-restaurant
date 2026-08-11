<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('order_item_id')->constrained('order_items')->onDelete('cascade');
            $table->foreignId('original_product_id')->nullable()->constrained('products')->onDelete('set null');
            $table->foreignId('substitute_product_id')->nullable()->constrained('products')->onDelete('set null');
            $table->foreignId('added_product_id')->nullable()->constrained('products')->onDelete('set null');
            
            // Ajuste de precio
            $table->decimal('price_adjustment', 10, 2)->default(0);
            $table->text('reason')->nullable();
            
            // Autorización
            $table->boolean('requires_authorization')->default(false);
            $table->foreignId('authorized_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            
            // Índices
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_modifiers');
    }
};
