<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Multi-tenancy
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            
            // Relaciones
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('menu_item_id')->nullable()->constrained('menu_items')->onDelete('set null');
            
            // Snapshot del item al momento del pedido
            $table->string('name_snapshot');
            $table->decimal('unit_price_snapshot', 10, 2);
            $table->integer('quantity')->default(1);
            $table->text('notes')->nullable();
            
            // Cálculos
            $table->decimal('subtotal', 10, 2);
            
            $table->timestamps();
            
            // Índices
            $table->index(['order_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
