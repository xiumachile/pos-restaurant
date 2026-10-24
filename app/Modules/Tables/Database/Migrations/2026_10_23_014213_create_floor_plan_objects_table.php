<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floor_plan_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            
            // Tipo de objeto: wall, pillar, plant, counter, decoration, etc.
            $table->string('type', 50);
            
            // Propiedades visuales
            $table->integer('position_x')->default(0);
            $table->integer('position_y')->default(0);
            $table->integer('rotation')->default(0);
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            
            // Metadatos adicionales (color, label, etc.) en JSON
            $table->json('properties')->nullable();
            
            $table->timestamps();
            
            $table->index(['branch_id', 'type'], 'idx_objects_branch_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floor_plan_objects');
    }
};
