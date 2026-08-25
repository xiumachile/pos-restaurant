<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['menu_id', 'product_id']);
            $table->index(['menu_id', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_products');
    }
};
