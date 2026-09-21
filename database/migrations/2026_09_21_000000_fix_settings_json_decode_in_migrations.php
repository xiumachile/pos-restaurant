<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Corrige el problema de migraciones que bypassean los casts de Eloquent.
 * 
 * Problema: Las migraciones 2026_08_15_000003 y 2026_09_07_100000 usan
 * DB::table('companies') en lugar del modelo Eloquent, lo que significa
 * que los casts no se aplican y settings se devuelve como string JSON
 * en lugar de array.
 * 
 * Esta migración:
 * 1. Valida que todas las companies tengan settings válido (JSON parseable)
 * 2. Corrige cualquier settings corrupto o malformado
 * 3. Asegura que el bloque currency esté correctamente estructurado
 * 
 * Principio: ADR-018 Money and Tax Architecture
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaultCurrency = [
            'code' => 'CLP',
            'symbol' => '$',
            'decimals' => 0,
            'thousands_separator' => '.',
            'decimal_separator' => ',',
        ];

        DB::table('companies')->orderBy('id')->chunk(100, function ($companies) use ($defaultCurrency) {
            foreach ($companies as $company) {
                $needsUpdate = false;
                $settings = null;

                // 1. Parsear settings de forma robusta
                if (is_string($company->settings)) {
                    $decoded = json_decode($company->settings, true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::warning("Company {$company->id}: settings JSON inválido, usando defaults", [
                            'error' => json_last_error_msg(),
                            'raw_settings' => substr($company->settings, 0, 200),
                        ]);
                        $settings = [];
                        $needsUpdate = true;
                    } else {
                        $settings = $decoded ?? [];
                    }
                } elseif (is_array($company->settings)) {
                    $settings = $company->settings;
                } else {
                    Log::warning("Company {$company->id}: settings es tipo inesperado", [
                        'type' => gettype($company->settings),
                    ]);
                    $settings = [];
                    $needsUpdate = true;
                }

                // 2. Validar y normalizar el bloque currency
                if (!isset($settings['currency']) || !is_array($settings['currency'])) {
                    $currentCode = 'CLP';
                    
                    if (isset($settings['currency']) && is_string($settings['currency'])) {
                        $currentCode = $settings['currency'];
                    }
                    
                    $settings['currency'] = array_merge($defaultCurrency, [
                        'code' => $currentCode,
                    ]);
                    
                    $needsUpdate = true;
                    
                    Log::info("Company {$company->id}: currency normalizado", [
                        'currency' => $settings['currency'],
                    ]);
                }

                // 3. Validar estructura de currency
                $currency = $settings['currency'];
                $requiredKeys = ['code', 'symbol', 'decimals', 'thousands_separator', 'decimal_separator'];
                $missingKeys = array_diff($requiredKeys, array_keys($currency));
                
                if (!empty($missingKeys)) {
                    $settings['currency'] = array_merge($defaultCurrency, $currency);
                    $needsUpdate = true;
                    
                    Log::info("Company {$company->id}: currency completado con defaults", [
                        'missing_keys' => $missingKeys,
                    ]);
                }

                // 4. Actualizar si es necesario
                if ($needsUpdate) {
                    try {
                        DB::table('companies')
                            ->where('id', $company->id)
                            ->update([
                                'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                'updated_at' => now(),
                            ]);
                        
                        Log::info("Company {$company->id}: settings actualizado correctamente");
                    } catch (\Exception $e) {
                        Log::error("Company {$company->id}: error al actualizar settings", [
                            'error' => $e->getMessage(),
                            'settings' => $settings,
                        ]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // No hay rollback: esta migración solo valida y corrige datos
        Log::info('Fix settings migration: down() llamado (no-op)');
    }
};
