<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Branches\Domain\Entities\Terminal;
use Modules\Identity\Domain\Entities\User;

class BaseTenantSeeder extends Seeder
{
    public function run(): void
    {
        // ============================================
        // 1. EMPRESA (Tenant raíz)
        // ============================================
        $company = Company::updateOrCreate(
            ['tax_id' => '76123456-7'],
            [
                'legal_name' => 'Restaurante Demo SpA',
                'trade_name' => 'Demo Restaurant',
                'default_locale' => 'es-CL',
                'fallback_locale' => 'es-CL',
                'is_active' => true,
                'settings' => [
                    'timezone' => 'America/Santiago',
                    'currency' => 'CLP',
                ],
            ]
        );

        $this->command->info("✅ Empresa creada: {$company->trade_name} ({$company->uuid})");

        // ============================================
        // 2. SUCURSALES (2)
        // ============================================
        $branchCentro = Branch::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'SCL-001'],
            [
                'name' => 'Sucursal Centro',
                'address' => 'Av. Providencia 1234, Santiago',
                'phone' => '+56 2 2345 6789',
                'default_locale' => 'es-CL',
                'tip_percentage_suggested' => 10.00,
                'is_active' => true,
            ]
        );

        $branchProvidencia = Branch::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'SCL-002'],
            [
                'name' => 'Sucursal Providencia',
                'address' => 'Av. Providencia 5678, Santiago',
                'phone' => '+56 2 2987 6543',
                'default_locale' => 'zh-CN', // Sucursal bilingüe con chino
                'tip_percentage_suggested' => 10.00,
                'is_active' => true,
            ]
        );

        $this->command->info("✅ Sucursales creadas: {$branchCentro->name}, {$branchProvidencia->name}");

        // ============================================
        // 3. TERMINALES (4: 2 POS + 2 KDS)
        // ============================================
        $terminals = [
            ['branch' => $branchCentro, 'code' => 'POS-001', 'name' => 'Caja Principal Centro', 'is_pos' => true, 'is_kds' => false, 'locale' => 'es-CL'],
            ['branch' => $branchCentro, 'code' => 'KDS-001', 'name' => 'Cocina Centro', 'is_pos' => false, 'is_kds' => true, 'locale' => 'zh-CN'],
            ['branch' => $branchProvidencia, 'code' => 'POS-002', 'name' => 'Caja Principal Providencia', 'is_pos' => true, 'is_kds' => false, 'locale' => 'es-CL'],
            ['branch' => $branchProvidencia, 'code' => 'KDS-002', 'name' => 'Cocina Providencia', 'is_pos' => false, 'is_kds' => true, 'locale' => 'zh-CN'],
        ];

        foreach ($terminals as $t) {
            Terminal::updateOrCreate(
                ['branch_id' => $t['branch']->id, 'code' => $t['code']],
                [
                    'company_id' => $company->id,
                    'name' => $t['name'],
                    'locale' => $t['locale'],
                    'is_pos' => $t['is_pos'],
                    'is_kds' => $t['is_kds'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('✅ 4 terminales creadas (2 POS + 2 KDS)');

        // ============================================
        // 4. USUARIOS (6 con diferentes roles)
        // ============================================
        $users = [
            ['email' => 'admin@demo.cl', 'name' => 'Admin Principal', 'role' => 'admin', 'branch' => $branchCentro, 'pin' => '1234'],
            ['email' => 'manager.centro@demo.cl', 'name' => 'Encargado Centro', 'role' => 'manager', 'branch' => $branchCentro, 'pin' => '2345'],
            ['email' => 'cajero.centro@demo.cl', 'name' => 'Cajero Centro', 'role' => 'cashier', 'branch' => $branchCentro, 'pin' => '3456'],
            ['email' => 'garzon.centro@demo.cl', 'name' => 'Garzón Centro', 'role' => 'waiter', 'branch' => $branchCentro, 'pin' => '4567'],
            ['email' => 'cocina.centro@demo.cl', 'name' => 'Cocinero Centro', 'role' => 'kitchen', 'branch' => $branchCentro, 'pin' => '5678'],
            ['email' => 'manager.prov@demo.cl', 'name' => 'Encargado Providencia', 'role' => 'manager', 'branch' => $branchProvidencia, 'pin' => '6789'],
        ];

        foreach ($users as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => 'password123',
                    'company_id' => $company->id,
                    'branch_id' => $u['branch']->id,
                    'role' => $u['role'],
                    'locale' => 'es-CL',
                    'is_active' => true,
                ]
            );
            $user->setPosPin($u['pin']);
            $user->save();
        }

        $this->command->info('✅ 6 usuarios creados (admin, 2 managers, cashier, waiter, kitchen)');

        // ============================================
        // Resumen
        // ============================================
        $this->command->info('');
        $this->command->info('=== RESUMEN DE DATOS SEMBRADOS ===');
        $this->command->info("Empresas:   " . Company::count());
        $this->command->info("Sucursales: " . Branch::count());
        $this->command->info("Terminales: " . Terminal::count());
        $this->command->info("Usuarios:   " . User::count());
    }
}
