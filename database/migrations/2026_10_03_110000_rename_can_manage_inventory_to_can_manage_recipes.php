<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ADR-022 / Fase 3.1: Renombrar capability en la base de datos.
     * 
     * En el Bloque 7 de la consolidación Inventory → Recipes, renombramos
     * la capability en el código (CapabilityKey enum + frontend) pero no
     * en los datos existentes de company_capabilities.
     * 
     * Esta migración sincroniza los datos con el nuevo nombre:
     * can_manage_inventory → can_manage_recipes
     * 
     * Seguridad: UPDATE idempotente (si ya existe can_manage_recipes,
     * no hace nada para evitar violar UNIQUE constraint si lo hubiera).
     */
    public function up(): void
    {
        // Verificar que no exista ya el nuevo nombre (evitar duplicados)
        $existsNew = DB::table('company_capabilities')
            ->where('capability_key', 'can_manage_recipes')
            ->exists();

        if ($existsNew) {
            // Si ya existe, solo eliminar el viejo
            DB::table('company_capabilities')
                ->where('capability_key', 'can_manage_inventory')
                ->delete();
            return;
        }

        // Renombrar: UPDATE capability_key
        DB::table('company_capabilities')
            ->where('capability_key', 'can_manage_inventory')
            ->update([
                'capability_key' => 'can_manage_recipes',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Rollback: renombrar de vuelta
        DB::table('company_capabilities')
            ->where('capability_key', 'can_manage_recipes')
            ->update([
                'capability_key' => 'can_manage_inventory',
                'updated_at' => now(),
            ]);
    }
};
