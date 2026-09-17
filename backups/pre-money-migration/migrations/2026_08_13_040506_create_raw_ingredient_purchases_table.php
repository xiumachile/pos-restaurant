<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_ingredient_purchases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('raw_ingredient_id')->constrained('raw_ingredients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('purchase_unit_name', 50);
            $table->decimal('purchase_quantity', 12, 2);
            $table->decimal('conversion_factor_to_base', 14, 4);
            $table->decimal('total_base_quantity_added', 14, 4);
            $table->decimal('total_purchase_cost', 12, 2);
            $table->decimal('calculated_cost_per_base_unit', 14, 6);
            $table->timestamp('purchase_date')->useCurrent();
            $table->timestamps();

            $table->index(['raw_ingredient_id', 'purchase_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_ingredient_purchases');
    }
};
