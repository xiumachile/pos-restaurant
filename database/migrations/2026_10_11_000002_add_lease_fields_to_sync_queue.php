<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O-03 FIX: Agregar campos para manejar timeouts y recuperación de jobs atrapados.
     */
    public function up(): void
    {
        Schema::table('sync_queue', function (Blueprint $table) {
            $table->timestamp('processing_started_at')->nullable()->after('status');
            $table->timestamp('lease_expires_at')->nullable()->after('processing_started_at');
            $table->string('last_error_code', 50)->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('sync_queue', function (Blueprint $table) {
            $table->dropColumn(['processing_started_at', 'lease_expires_at', 'last_error_code']);
        });
    }
};
