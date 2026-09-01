<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega unique constraint a (reference_type, reference_id) en journal_entries.
 *
 * P0-06 — Idempotencia en Ledger
 *
 * Garantiza que solo pueda existir UN asiento contable por documento fuente.
 * Esto previene asientos duplicados si LedgerService.createJournalEntry()
 * se llama múltiples veces con la misma referencia (ej: retry manual, bug).
 *
 * Combinado con la verificación en LedgerService, esto provee:
 * 1. Idempotencia a nivel de aplicación (verificación previa)
 * 2. Defensa última a nivel de DB (unique constraint)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Primero eliminamos el índice existente (que no es unique)
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['reference_type', 'reference_id']);
        });

        // Luego creamos el índice como unique
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unique(['reference_type', 'reference_id'], 'journal_entries_reference_unique');
        });
    }

    public function down(): void
    {
        // Revertir: eliminar unique y recrear como índice normal
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique('journal_entries_reference_unique');
            $table->index(['reference_type', 'reference_id']);
        });
    }
};
