<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Companies\Domain\ValueObjects\CapabilityKey;

/**
 * Habilita todas las capabilities por defecto para empresas existentes.
 *
 * Esto asegura que al proteger rutas con middleware 'capability:xxx',
 * las empresas que ya estaban en el sistema no queden bloqueadas.
 *
 * En el futuro, se podrá usar un seeder más específico según el tipo
 * de restaurante, pero por ahora todas las empresas tienen acceso
 * completo a todas las capabilities.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Obtener todas las companies que no tienen capabilities definidas
        $companies = DB::table('companies')
            ->whereNull('deleted_at')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('company_capabilities')
                    ->whereColumn('company_capabilities.company_id', 'companies.id');
            })
            ->get();

        $now = now();

        foreach ($companies as $company) {
            foreach (CapabilityKey::cases() as $capabilityKey) {
                DB::table('company_capabilities')->insert([
                    'company_id' => $company->id,
                    'capability_key' => $capabilityKey->value,
                    'is_enabled' => true,
                    'settings' => '{}',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if ($companies->isNotEmpty()) {
            echo "✅ Habilitadas 8 capabilities para {$companies->count()} empresas existentes\n";
        } else {
            echo "ℹ️  No hay empresas sin capabilities para actualizar\n";
        }
    }

    public function down(): void
    {
        // No revertimos: eliminar capabilities sería destructivo
        // Si se necesita rollback, eliminar manualmente los registros
    }
};
