<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El campo role en users es un string, no requiere cambio de schema
        // Solo documentamos que ahora 'super_admin' es un rol válido
        // Los valores posibles son: super_admin, admin, manager, cashier, waiter, kitchen
        
        // Verificar que el campo role existe y es string (no enum)
        if (Schema::hasColumn('users', 'role')) {
            // Nada que cambiar, solo validamos
        }
    }

    public function down(): void
    {
        // No hay rollback necesario, el campo ya es string
    }
};
