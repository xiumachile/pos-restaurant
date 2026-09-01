<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Actualiza el CHECK constraint de la columna status en payments
 * para permitir todos los valores del enum PaymentStatus actual:
 * - pending
 * - completed
 * - refunded
 * - failed
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');

        DB::statement("
            ALTER TABLE payments
            ADD CONSTRAINT payments_status_check
            CHECK (status IN ('pending', 'completed', 'refunded', 'failed'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');

        // Revertir a la versión anterior (asumiendo solo pending/completed/failed)
        DB::statement("
            ALTER TABLE payments
            ADD CONSTRAINT payments_status_check
            CHECK (status IN ('pending', 'completed', 'failed'))
        ");
    }
};
