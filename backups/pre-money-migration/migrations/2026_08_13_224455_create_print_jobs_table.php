<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('print_jobs')) {
            Schema::create('print_jobs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('printer_id')->constrained('printers')->cascadeOnDelete();
                
                // Contexto del trabajo
                $table->string('job_type', 50); // kitchen_command, bar_command, receipt
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                
                // Contenido ESC/POS (bytes raw)
                $table->binary('escpos_bytes');
                
                // Estado
                $table->string('status', 30)->default('pending'); // pending, printing, completed, failed
                $table->integer('attempts')->default(0);
                $table->integer('max_attempts')->default(3);
                $table->text('error_message')->nullable();
                $table->timestamp('printed_at')->nullable();
                
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'branch_id', 'status']);
                $table->index(['printer_id', 'status']);
                $table->index(['order_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
