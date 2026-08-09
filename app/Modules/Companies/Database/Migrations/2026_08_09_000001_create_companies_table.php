<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pgcrypto solo existe en PostgreSQL
    if (DB::getDriverName() === 'pgsql') {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');
    }

    Schema::create('companies', function (Blueprint $table) {
        $table->id();
        $table->uuid('uuid')->unique();

            // Datos legales y comerciales
            $table->string('tax_id', 30)->unique()->comment('RUT/NIT/CUIT');
            $table->string('legal_name', 255);
            $table->string('trade_name', 255);

            // i18n
            $table->string('default_locale', 10)->default('es-CL');
            $table->string('fallback_locale', 10)->default('es-CL');

            // Estado
            $table->boolean('is_active')->default(true);

            // Configuración flexible (timezone, currency, etc.)
            $table->jsonb('settings')->default('{}');

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('is_active');
            $table->index('tax_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
