<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('recipe_id')->constrained('product_recipes')->cascadeOnDelete();
            $table->foreignId('raw_ingredient_id')->constrained('raw_ingredients')->cascadeOnDelete();
            $table->decimal('quantity_base_unit', 14, 4);
            $table->decimal('waste_percentage', 5, 2)->default(0);
            $table->decimal('effective_discount_base_quantity', 14, 4);
            $table->decimal('calculated_item_cost', 12, 2);
            $table->timestamps();

            $table->index(['recipe_id']);
            $table->index(['raw_ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_items');
    }
};
