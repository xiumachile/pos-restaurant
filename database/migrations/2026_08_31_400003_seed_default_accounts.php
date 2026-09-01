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
        // Usar Account::seedDefaultsFor que es 100% idempotente (usa upsert)
        \Modules\Accounting\Domain\Entities\Account::seedDefaultsFor($companyId);
    }

    public function down(): void
    {
        // No hacer nada en down (las cuentas son datos maestros)
    }
};
