<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P1-011: Agregar processing_until para prevenir zombie locks de 24h.
     * 
     * Si un proceso muere después de crear el registro de idempotencia 
     * pero antes de guardar la respuesta, el registro queda con 
     * response_code = null. Sin esta columna, el siguiente request 
     * recibiría 409 request_in_progress hasta que expire el TTL (24h).
     * 
     * Con processing_until, si el lease expira (ej. 60s), el nuevo 
     * request puede "robar" el claim y completar la operación.
     */
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->timestamp('processing_until')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropColumn('processing_until');
        });
    }
};
