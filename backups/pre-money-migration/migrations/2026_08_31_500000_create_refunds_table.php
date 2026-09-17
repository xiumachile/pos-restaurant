<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla refunds para reembolsos de pagos.
 *
 * P0-04 — Refund / Reversal / Allocation
 *
 * Cada refund:
 * - Referencia al payment original
 * - Tiene un amount (puede ser parcial o total)
 * - Genera un journal_entry de reversa cuando se completa
 * - Usa idempotency_key para evitar duplicados
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->string('refund_number', 50)->unique();
            $table->decimal('amount', 15, 2);
            $table->string('status', 30); // pending, completed, failed, cancelled
            $table->string('reason', 500)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->onDelete('set null');
            $table->text('notes')->nullable();
            $table->uuid('idempotency_key')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['payment_id', 'status']);
            $table->index(['company_id', 'created_at']);
            $table->unique(['company_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
