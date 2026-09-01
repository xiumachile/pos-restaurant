<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Companies\Domain\Entities\Company;

return new class extends Migration
{
    public function up(): void
    {
        // Crear cuentas contables por defecto para cada empresa existente
        $companies = Company::all();

        foreach ($companies as $company) {
            $this->createDefaultAccounts($company->id);
        }
    }

    private function createDefaultAccounts(int $companyId): void
    {
        $accounts = [
            ['code' => '1100', 'name' => 'Efectivo en Caja', 'type' => 'asset', 'description' => 'Efectivo físico en caja'],
            ['code' => '1200', 'name' => 'Bancos', 'type' => 'asset', 'description' => 'Cuentas bancarias'],
            ['code' => '1300', 'name' => 'Por Cobrar Tarjetas', 'type' => 'asset', 'description' => 'Pagos con tarjeta por liquidar'],
            ['code' => '2100', 'name' => 'IVA por Pagar', 'type' => 'liability', 'description' => 'IVA收集的 por pagar al SII'],
            ['code' => '2200', 'name' => 'Propinas por Pagar', 'type' => 'liability', 'description' => 'Propinas收集的 por pagar a garzones'],
            ['code' => '4100', 'name' => 'Ingresos por Ventas', 'type' => 'revenue', 'description' => 'Ingresos por ventas de productos/servicios'],
            ['code' => '5100', 'name' => 'Costo de Ventas', 'type' => 'expense', 'description' => 'Costo de los productos vendidos'],
            ['code' => '5200', 'name' => 'Gastos Operativos', 'type' => 'expense', 'description' => 'Gastos generales del negocio'],
        ];

        foreach ($accounts as $account) {
            DB::table('accounts')->insert([
                'uuid' => \Illuminate\Support\Str::uuid(),
                'company_id' => $companyId,
                'branch_id' => null,
                'code' => $account['code'],
                'name' => $account['name'],
                'type' => $account['type'],
                'description' => $account['description'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No hacer nada en down (las cuentas son datos maestros)
    }
};
