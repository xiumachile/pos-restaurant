# Mejores Prácticas para Migraciones

## Problema Identificado: Bypass de Casts de Eloquent

### Contexto

Las migraciones que usan `DB::table()` en lugar de modelos Eloquent **no aplican los casts** definidos en los modelos. Esto puede causar errores cuando se accede a columnas JSON/JSONB que están casteadas como `array` o `object`.

### Ejemplo del Problema

```php
// ❌ INCORRECTO: Bypassea los casts
DB::table('companies')->chunk(100, function ($companies) {
    foreach ($companies as $company) {
        // $company->settings es STRING (JSON crudo), no array
        $currency = $company->settings['currency']; // 💥 Error: Cannot access offset of type string on string
    }
});

// ✅ CORRECTO: Usa el modelo Eloquent
Company::withoutGlobalScopes()->chunk(100, function ($companies) {
    foreach ($companies as $company) {
        // $company->settings es ARRAY (cast aplicado automáticamente)
        $currency = $company->settings['currency']; // ✅ Funciona
    }
});

Casos de Uso
1. Migraciones que manipulan datos existentes
Incorrecto:
public function up(): void
{
    $companies = DB::table('companies')->get();
    foreach ($companies as $company) {
        $settings = $company->settings; // String JSON, no array
        $settings['new_key'] = 'value';
        
        DB::table('companies')
            ->where('id', $company->id)
            ->update(['settings' => json_encode($settings)]);
    }
}

Correcto:
public function up(): void
{
    $companies = Company::withoutGlobalScopes()->get();
    foreach ($companies as $company) {
        $settings = $company->settings; // Array (cast aplicado)
        $settings['new_key'] = 'value';
        
        $company->settings = $settings;
        $company->save();
    }
}

2. Cuando no se puede usar el modelo
Si por alguna razón no puedes usar el modelo Eloquent (ej: tabla no tiene modelo, migración muy temprana), debes hacer el decode manualmente:
public function up(): void
{
    DB::table('companies')->chunk(100, function ($companies) {
        foreach ($companies as $company) {
            // Decode manual robusto
            $settings = is_string($company->settings)
                ? json_decode($company->settings, true) ?? []
                : ($company->settings ?? []);
            
            // Validar que sea array
            if (!is_array($settings)) {
                Log::warning("Settings inválido para company {$company->id}");
                $settings = [];
            }
            
            $settings['new_key'] = 'value';
            
            DB::table('companies')
                ->where('id', $company->id)
                ->update([
                    'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }
    });
}

Reglas Generales
1.Preferir modelos Eloquent sobre DB::table() cuando sea posible
2.Usar withoutGlobalScopes() si necesitas evitar filtros de tenancy
3. Validar el tipo después de json_decode() (puede fallar con JSON inválido)
4. Usar flags JSON al encodear: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
5. Loggear errores para debugging en producción
6. Hacer migraciones idempotentes (pueden ejecutarse múltiples veces sin error)
Migraciones Afectadas en Este Proyecto
2026_08_15_000003_migrate_tax_data.php - Usa DB::table('companies')
2026_09_07_100000_add_currency_config_to_company_settings.php - Usa DB::table('companies')

Solución Aplicada
Migración 2026_09_21_000000_fix_settings_json_decode_in_migrations.php que:
Valida que todas las companies tengan settings JSON válido
Corrige settings corruptos o malformados
Asegura estructura correcta del bloque currency
Loggea todos los cambios para auditoría
Referencias
Laravel Eloquent: Mutators & Casting
ADR-018: Money and Tax Architecture
PostgreSQL JSON Types
