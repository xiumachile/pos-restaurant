<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('code', 50);
            $table->jsonb('name_translations');
            $table->string('type', 30); // cash, card, transfer, gift_card, other
            $table->string('icon')->nullable();
            $table->decimal('max_amount', 14, 2)->nullable();
            $table->boolean('requires_reference')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'branch_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
