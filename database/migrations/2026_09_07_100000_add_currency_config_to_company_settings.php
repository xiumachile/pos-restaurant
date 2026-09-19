<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normaliza la configuración de moneda en el campo settings de todas
     * las companies existentes.
     *
     * Estructura final:
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
     * Maneja tres casos:
     *  - settings NULL o vacío       → crea el bloque completo con defaults
     *  - currency es string ("CLP")  → lo convierte a objeto preservando el code
     *  - currency ya es objeto       → no toca nada (idempotente)
     *
     * Principio: Money and Tax Architecture (docs/architecture/money-and-tax.md)
     */
    public function up(): void
    {
        $default = [
            'code' => 'CLP',
            'symbol' => '$',
            'decimals' => 0,
            'thousands_separator' => '.',
            'decimal_separator' => ',',
        ];

        DB::table('companies')->orderBy('id')->chunk(100, function ($companies) use ($default) {
            foreach ($companies as $company) {
                $settings = is_string($company->settings)
                    ? json_decode($company->settings, true)
                    : ($company->settings ?? []);

                if (!is_array($settings['currency'] ?? null)) {
                    $settings['currency'] = array_merge($default, [
                        'code' => is_string($settings['currency'] ?? null)
                            ? $settings['currency']
                            : 'CLP',
                    ]);

                    DB::table('companies')
                        ->where('id', $company->id)
                        ->update([
                            'settings' => json_encode($settings),
                            'updated_at' => now(),
                        ]);
                }
            }
        });
    }

    /**
     * Elimina el bloque currency de todas las companies.
     */
    public function down(): void
    {
        DB::table('companies')->orderBy('id')->chunk(100, function ($companies) {
            foreach ($companies as $company) {
                $settings = is_string($company->settings)
                    ? json_decode($company->settings, true)
                    : ($company->settings ?? []);

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
        });
    }
};
