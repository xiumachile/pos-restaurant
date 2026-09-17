<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tip_payouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->bigInteger('company_id');
            $table->bigInteger('branch_id');
            $table->bigInteger('cash_session_id');
            
            // Quién entrega y quién recibe
            $table->bigInteger('processed_by');    // Cajero que entrega
            $table->bigInteger('waiter_id');       // Garzón que recibe
            
            // Monto y método
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 50);  // cash | card | transfer
            
            // Política aplicada
            $table->string('policy_type', 50);     // waiter_keeps | shared_pool | percentage_split
            
            $table->text('notes')->nullable();
            $table->boolean('is_voided')->default(false);
            $table->timestamp('voided_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['company_id', 'branch_id', 'cash_session_id']);
            $table->index(['waiter_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tip_payouts');
    }
};
