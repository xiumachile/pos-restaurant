<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('price_list_id')->constrained('price_lists');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('CLP');
            $table->timestamps();

            $table->unique(['product_id', 'price_list_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
