<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Illuminate\Support\Facades\Hash;

class DevSeeder extends Seeder
{
    public function run(): void
    {
        // Compañía
        $company = Company::withoutGlobalScopes()
            ->where('trade_name', 'Wok & Mesa')
            ->first();

        if ($company) {
            $this->command->info("Compañía ya existe: {$company->trade_name} (ID: {$company->id})");
        } else {
            $taxId = '76.999.888-' . rand(0, 9);
            $company = Company::withoutGlobalScopes()->create([
                'tax_id' => $taxId,
                'legal_name' => 'Wok & Mesa SpA',
                'trade_name' => 'Wok & Mesa',
                'default_locale' => 'es-CL',
                'fallback_locale' => 'zh-CN',
                'is_active' => true,
            ]);
            $this->command->info("Compañía creada: {$company->trade_name} (ID: {$company->id})");
        }

        // Branch
        $branch = Branch::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('code', 'MAIN')
            ->first();

        if (!$branch) {
            $branch = Branch::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'code' => 'MAIN',
                'name' => 'Sucursal Principal',
                'address' => 'Av. Providencia 1234, Santiago',
                'phone' => '+56 2 2345 6789',
                'default_locale' => 'es-CL',
                'tip_percentage_suggested' => 10.0,
                'allow_negative_stock' => false,
                'is_active' => true,
            ]);
            $this->command->info("Branch creada: {$branch->name} (ID: {$branch->id})");
        } else {
            $this->command->info("Branch ya existe: {$branch->name} (ID: {$branch->id})");
        }

        // Usuarios con pos_pin_hash (columna real del modelo)
        $users = [
            ['name' => 'Admin Demo', 'email' => 'admin@wokmesa.cl', 'role' => 'admin', 'pin' => null],
            ['name' => 'Gerente Demo', 'email' => 'manager@wokmesa.cl', 'role' => 'manager', 'pin' => null],
            ['name' => 'Cajero Demo', 'email' => 'cashier@wokmesa.cl', 'role' => 'cashier', 'pin' => '1234'],
            ['name' => 'Garzón Demo', 'email' => 'waiter@wokmesa.cl', 'role' => 'waiter', 'pin' => '5678'],
            ['name' => 'Cocina Demo', 'email' => 'kitchen@wokmesa.cl', 'role' => 'kitchen', 'pin' => '9999'],
        ];

        foreach ($users as $userData) {
            $pin = $userData['pin'];
            unset($userData['pin']);

            $user = User::withoutGlobalScopes()
                ->where('email', $userData['email'])
                ->first();

            if (!$user) {
                $attributes = array_merge($userData, [
                    'password' => Hash::make('password123'),
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'is_active' => true,
                ]);

                if ($pin !== null) {
                    $attributes['pos_pin_hash'] = Hash::make($pin);
                }

                $user = User::withoutGlobalScopes()->create($attributes);
                $pinInfo = $pin ? " - PIN: {$pin}" : "";
                $this->command->info("Usuario creado: {$user->email} ({$user->role}){$pinInfo}");
            } else {
                if ($pin !== null) {
                    $user->pos_pin_hash = Hash::make($pin);
                    $user->save();
                }
                $pinInfo = $pin ? " - PIN: {$pin}" : "";
                $this->command->info("Usuario ya existe: {$user->email} ({$user->role}){$pinInfo}");
            }
        }

        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════');
        $this->command->info('  CREDENCIALES PARA LOGIN');
        $this->command->info('═══════════════════════════════════════════════');
        $this->command->info('');
        $this->command->info('  Email/Password (todos usan password123):');
        $this->command->info('    admin@wokmesa.cl    | manager@wokmesa.cl');
        $this->command->info('    cashier@wokmesa.cl  | waiter@wokmesa.cl');
        $this->command->info('    kitchen@wokmesa.cl');
        $this->command->info('');
        $this->command->info('  PIN POS: Cajero=1234 | Garzón=5678 | Cocina=9999');
        $this->command->info('');
    }
}
