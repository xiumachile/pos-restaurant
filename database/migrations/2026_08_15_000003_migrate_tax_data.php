<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Obtener todas las empresas
        $companies = DB::table('companies')->get();

        foreach ($companies as $company) {
            // Crear impuesto IVA 19% por defecto
            $taxId = DB::table('taxes')->insertGetId([
                'uuid' => Str::uuid(),
                'company_id' => $company->id,
                'name' => 'IVA 19%',
                'code' => 'IVA',
                'type' => 'percent',
                'rate' => 19.0000,
                'is_default' => true,
                'is_active' => true,
                'description' => 'Impuesto al Valor Agregado estándar de Chile',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Asociar todos los productos de esta empresa al IVA 19%
            DB::table('products')
                ->where('company_id', $company->id)
                ->whereNull('tax_id')
                ->update(['tax_id' => $taxId]);

            // Asociar todas las categorías de esta empresa al IVA 19%
            DB::table('categories')
                ->where('company_id', $company->id)
                ->whereNull('tax_id')
                ->update(['tax_id' => $taxId]);
        }
    }

    public function down(): void
    {
        // Limpiar tax_id de products y categories
        DB::table('products')->update(['tax_id' => null]);
        DB::table('categories')->update(['tax_id' => null]);
        
        // Eliminar todos los taxes (solo los creados por esta migración)
        DB::table('taxes')->where('code', 'IVA')->delete();
    }
};
