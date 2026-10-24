<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floor_plan_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            
            // Dimensiones del lienzo en píxeles
            $table->integer('canvas_width')->default(2000);
            $table->integer('canvas_height')->default(1500);
            $table->integer('grid_size')->default(40);
            
            $table->unique('branch_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floor_plan_settings');
    }
};
