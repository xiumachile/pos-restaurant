<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        throw new RuntimeException(
            'SuperAdminSeeder está deshabilitado por seguridad. ' .
            'Utilice php artisan platform:bootstrap-super-admin ' .
            'desde una consola administrativa interactiva.'
        );
    }
}
