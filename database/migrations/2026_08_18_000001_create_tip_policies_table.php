<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tip_policies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->bigInteger('company_id');
            $table->bigInteger('branch_id')->nullable();
            
            // Política principal
            $table->string('policy_type', 50)->default('waiter_keeps');
            // Valores: waiter_keeps | shared_pool | percentage_split
            
            // Manejo de propinas con tarjeta
            $table->string('card_tip_handling', 50)->default('cash_payout');
            // Valores: cash_payout | payroll | mixed
            
            // Método de reparto del pozo (si aplica)
            $table->string('pool_split_method', 50)->default('equal');
            // Valores: equal | by_hours | by_points
            
            // Porcentajes (si policy_type = percentage_split)
            $table->decimal('waiter_percentage', 5, 2)->default(100);
            $table->decimal('pool_percentage', 5, 2)->default(0);
            
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['company_id', 'branch_id']);
            $table->unique(['company_id', 'branch_id', 'effective_from'], 'tip_policy_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tip_policies');
    }
};
