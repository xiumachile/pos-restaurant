<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name', 100)->comment('Identificador: precio_comedor, precio_delivery...');
            $table->string('display_name', 100)->nullable()->comment('Nombre visible: Precio Comedor');
            $table->string('channel_type', 50)->nullable()->comment('Hint opcional: dine_in, delivery, uber_eats...');
            $table->string('currency', 3)->default('CLP');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_lists');
    }
};
