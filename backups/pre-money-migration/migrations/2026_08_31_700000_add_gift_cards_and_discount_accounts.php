<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Accounting\Domain\Entities\Account;
use Modules\Companies\Domain\Entities\Company;

/**
 * Agrega cuentas 1400 (Gift Cards) y 4200 (Descuentos) a empresas existentes.
 * 
 * P0-05 — Integración Ledger ↔ PaymentService
 * 
 * Estas cuentas son necesarias para contabilizar:
 * - Pagos con gift_card (van a 1400)
 * - Descuentos aplicados a pedidos (van a 4200)
 */
return new class extends Migration
{
    public function up(): void
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            Account::seedDefaultsFor($company->id);
        }
    }

    public function down(): void
    {
        // No eliminar cuentas (podrían tener movimientos)
    }
};
