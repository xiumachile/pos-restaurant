<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->after('order_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();
            
            $table->index(['order_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropIndex(['order_id', 'product_id']);
            $table->dropColumn('product_id');
        });
    }
};
