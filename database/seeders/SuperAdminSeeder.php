<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Domain\Entities\User;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;

class SuperAdminSeeder extends Seeder
{
    /**
     * Crea el primer usuario super_admin del sistema.
     * 
     * Este usuario tiene acceso universal a todas las empresas
     * y es requerido para operaciones como POST /companies.
     * 
     * Uso:
     *   php artisan db:seed --class=SuperAdminSeeder
     * 
     * Credenciales por defecto:
     *   Email: admin@pos.local
     *   Password: password123 (cambiar en producción)
     */
    public function run(): void
    {
        // Verificar si ya existe
        $existing = User::where('email', 'admin@pos.local')->first();
        
        if ($existing) {
            $this->command->info('⚠️  Super admin ya existe: ' . $existing->email);
            return;
        }

        // Crear company y branch para el super_admin
        $company = Company::firstOrCreate(
            ['tax_id' => 'SUPER-ADMIN'],
            [
                'legal_name' => 'POS System Administration',
                'trade_name' => 'POS Admin',
            ]
        );

        $branch = Branch::firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'ADMIN',
            ],
            [
                'name' => 'Administration Branch',
            ]
        );

        // Crear super_admin
        $superAdmin = User::create([
            'name' => 'System Administrator',
            'email' => 'admin@pos.local',
            'password' => bcrypt('password123'),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'super_admin',
        ]);

        $this->command->info('✅ Super admin creado exitosamente');
        $this->command->info('   Email: admin@pos.local');
        $this->command->info('   Password: password123');
        $this->command->warn('⚠️  Cambiar password en producción inmediatamente');
    }
}
