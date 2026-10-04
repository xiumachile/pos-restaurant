<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PurchaseHistorySeeder extends Seeder
{
    public function run(): void
    {
        // Obtener insumos existentes y primer usuario manager/admin
        $ingredients = DB::table('raw_ingredients')->get();
        if ($ingredients->isEmpty()) {
            $this->command->warn('No hay insumos. Ejecuta RawIngredientSeeder primero.');
            return;
        }

        $user = DB::table('users')
            ->whereIn('role', ['manager', 'admin'])
            ->first();

        if (!$user) {
            $this->command->warn('No hay usuario manager/admin.');
            return;
        }

        // Proveedores ficticios
        $suppliers = [
            ['name' => 'Distribuidora Central', 'rut' => '76.123.456-7'],
            ['name' => 'Carnes Premium SpA', 'rut' => '77.888.999-K'],
            ['name' => 'Verduras del Sur', 'rut' => '78.222.333-4'],
            ['name' => 'Aceites y Especias Ltda', 'rut' => '79.444.555-6'],
            ['name' => 'Mercado Local', 'rut' => null], // sin RUT (boleta)
        ];

        // Documentos SII
        $docTypes = ['boleta', 'factura', 'factura_exenta'];

        $now = Carbon::now();
        $purchasesCreated = 0;

        // Crear ~50 compras distribuidas en los últimos 60 días
        for ($i = 0; $i < 50; $i++) {
            $ingredient = $ingredients->random();
            $supplier = $suppliers[array_rand($suppliers)];
            $docType = $docTypes[array_rand($docTypes)];
            $daysAgo = rand(0, 60);
            $purchaseDate = $now->copy()->subDays($daysAgo)->setTime(rand(8, 20), rand(0, 59));

            // Cantidad realista según unidad base
            $baseUnit = $ingredient->base_unit;
            $purchaseUnit = match ($baseUnit) {
                'g' => ['kg', 1000],
                'ml' => ['l', 1000],
                'un' => ['unidad', 1],
                default => ['kg', 1000],
            };

            $purchaseQuantity = match ($purchaseUnit[0]) {
                'kg', 'l' => rand(1, 20),
                'unidad' => rand(5, 50),
                default => rand(1000, 20000),
            };

            $totalBase = $purchaseQuantity * $purchaseUnit[1];

            // Costo realista según tipo de insumo
            $costPerBase = match (true) {
                str_contains($ingredient->sku ?? '', 'CARNE') => rand(8, 15),
                str_contains($ingredient->sku ?? '', 'ACEITE') => rand(3, 8),
                str_contains($ingredient->sku ?? '', 'VERD') => rand(1, 4),
                default => rand(2, 10),
            };

            $totalCost = (int) ($totalBase * $costPerBase);

            DB::table('raw_ingredient_purchases')->insert([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'raw_ingredient_id' => $ingredient->id,
                'user_id' => $user->id,
                'purchase_unit_name' => $purchaseUnit[0],
                'purchase_quantity' => $purchaseQuantity,
                'conversion_factor_to_base' => $purchaseUnit[1],
                'total_base_quantity_added' => $totalBase,
                'total_purchase_cost' => $totalCost,
                'calculated_cost_per_base_unit' => $costPerBase,
                'purchase_date' => $purchaseDate,
                'document_type' => rand(1, 10) <= 8 ? $docType : null, // 80% con doc
                'document_number' => rand(1, 10) <= 8 ? strtoupper(substr($docType, 0, 1)) . '-' . rand(1000, 9999) : null,
                'supplier_name' => $supplier['name'],
                'supplier_rut' => $supplier['rut'],
                'created_at' => $purchaseDate,
                'updated_at' => $purchaseDate,
            ]);

            $purchasesCreated++;
        }

        $this->command->info("✅ {$purchasesCreated} compras creadas en los últimos 60 días");
    }
}
