<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Multiempresa
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Identificación
            $table->string('code', 50);
            $table->string('name', 255);

            // Contacto
            $table->text('address')->nullable();
            $table->string('phone', 50)->nullable();

            // i18n
            $table->string('default_locale', 10)->default('es-CL');

            // Configuración comercial (sección 11.4 propinas)
            $table->decimal('tip_percentage_suggested', 5, 2)->default(10.00);
            $table->boolean('allow_negative_stock')->default(false);

            // Estado
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Unique: código único dentro de la empresa
            $table->unique(['company_id', 'code'], 'uk_branch_code');

            // Índices multiempresa
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
