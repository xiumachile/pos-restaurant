<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_ingredients', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('sku', 100);
            $table->jsonb('name_translations');
            $table->string('dimension_type', 20);
            $table->string('base_unit', 20);
            $table->decimal('current_stock_base', 14, 4)->default(0);
            $table->decimal('minimum_stock_base', 14, 4)->default(0);
            $table->decimal('cost_per_base_unit', 14, 6)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'sku']);
            $table->index(['company_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_ingredients');
    }
};
