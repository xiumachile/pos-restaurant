<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->uuid('key')->unique(); // UUID enviado por el cliente
            $table->string('request_hash', 64); // Hash del request body
            $table->json('response_body')->nullable(); // Respuesta cacheada
            $table->integer('response_code')->nullable(); // HTTP status code
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('endpoint', 255)->nullable(); // Para debugging
            $table->timestamp('expires_at')->index(); // TTL
            $table->timestamps();

            // Índice para búsqueda rápida por key + expiración
            $table->index(['key', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
