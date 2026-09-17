<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_activations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->string('channel_type', 30)->default('all')->comment('dine_in, delivery, uber_eats, rappi, all');
            $table->jsonb('days_of_week')->nullable()->comment('[1..7] ISO: 1=lunes, 7=domingo. null=todos');
            $table->time('time_from')->nullable()->comment('null = sin límite inferior');
            $table->time('time_to')->nullable()->comment('null = sin límite superior');
            $table->unsignedInteger('priority')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['menu_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_activations');
    }
};
