<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega configuración de moneda por defecto al campo settings de todas
     * las companies existentes.
     *
     * Estructura agregada:
     * {
     *   "currency": {
     *     "code": "CLP",
     *     "symbol": "$",
     *     "decimals": 0,
     *     "thousands_separator": ".",
     *     "decimal_separator": ","
     *   }
     * }
     *
     * Principio: Money and Tax Architecture (docs/architecture/money-and-tax.md)
     */
    public function up(): void
    {
        $companies = DB::table('companies')->get();

        foreach ($companies as $company) {
            $settings = $company->settings ?? [];
            
            // Solo agregar si no existe el bloque currency
            if (!isset($settings['currency'])) {
                $settings['currency'] = [
                    'code' => 'CLP',
                    'symbol' => '$',
                    'decimals' => 0,
                    'thousands_separator' => '.',
                    'decimal_separator' => ',',
                ];

                DB::table('companies')
                    ->where('id', $company->id)
                    ->update([
                        'settings' => json_encode($settings),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    /**
     * Remove currency block from all companies.
     */
    public function down(): void
    {
        $companies = DB::table('companies')->get();

        foreach ($companies as $company) {
            $settings = $company->settings ?? [];
            
            if (isset($settings['currency'])) {
                unset($settings['currency']);

                DB::table('companies')
                    ->where('id', $company->id)
                    ->update([
                        'settings' => json_encode($settings),
                        'updated_at' => now(),
                    ]);
            }
        }
    }
};
