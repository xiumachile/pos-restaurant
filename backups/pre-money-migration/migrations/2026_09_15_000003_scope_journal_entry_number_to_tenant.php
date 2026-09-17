<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1: Scope journal_entry_number a tenant
 * 
 * Problema: journal_entry_number tenía UNIQUE global, causando colisiones
 * cuando dos tenants creaban asientos el mismo día.
 * 
 * Fix: Cambiar UNIQUE de (journal_entry_number) a (company_id, journal_entry_number)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique(['journal_entry_number']);
            $table->unique(['company_id', 'journal_entry_number'], 'journal_entries_company_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique('journal_entries_company_number_unique');
            $table->unique(['journal_entry_number']);
        });
    }
};
