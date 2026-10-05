<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_numbering_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->unique()->constrained('branches')->onDelete('cascade');
            
            // Configuración de numeración
            $table->boolean('is_enabled')->default(false);
            $table->string('prefix', 10)->default('ORD');
            $table->enum('reset_frequency', ['daily', 'monthly'])->default('daily');
            $table->integer('current_sequence')->default(1);
            $table->date('last_reset_date')->nullable();
            
            $table->timestamps();
        });

        // Insertar configuración por defecto para todas las branches existentes
        DB::table('branches')->orderBy('id')->chunk(100, function ($branches) {
            foreach ($branches as $branch) {
                DB::table('order_numbering_config')->insert([
                    'branch_id' => $branch->id,
                    'is_enabled' => false,
                    'prefix' => 'ORD',
                    'reset_frequency' => 'daily',
                    'current_sequence' => 1,
                    'last_reset_date' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_numbering_config');
    }
};
