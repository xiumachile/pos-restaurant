<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Multi-tenancy
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            
            // Identificación
            $table->string('order_number')->unique();
            $table->enum('type', ['dine_in', 'takeout', 'delivery'])->default('dine_in');
            $table->enum('status', [
                'draft', 'confirmed', 'preparing', 'ready', 
                'served', 'paid', 'closed', 'cancelled'
            ])->default('draft');
            
            // Relaciones
            $table->foreignId('table_id')->nullable()->constrained('restaurant_tables')->onDelete('set null');
            $table->foreignId('waiter_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('cashier_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Totales
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            
            // Notas
            $table->text('notes')->nullable();
            
            // Timestamps de estado
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index(['company_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index(['table_id', 'status']);
            $table->index(['waiter_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
