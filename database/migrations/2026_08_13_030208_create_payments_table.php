<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->nullOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('payment_number', 50)->unique();
            $table->string('method_code', 50); // cash, card, transfer, gift_card
            $table->decimal('amount', 14, 2);
            $table->decimal('tip_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2); // amount + tip_amount
            $table->string('reference_code', 100)->nullable();
            $table->string('status', 30)->default('completed'); // pending, completed, refunded, failed
            $table->string('idempotency_key', 100)->unique();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'branch_id']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
