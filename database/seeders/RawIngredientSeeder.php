<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\ValueObjects\DimensionType;
use Modules\Recipes\Domain\ValueObjects\BaseUnit;

/**
 * Seeder de insumos de prueba para desarrollo.
 * Crea ingredientes típicos de un restaurante chino (Wok & Mesa).
 */
class RawIngredientSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = 1;
        $branchId = 1;

        // Verificar si ya existen insumos
        if (RawIngredient::withoutGlobalScopes()->where('company_id', $companyId)->count() > 0) {
            $this->command->info('Ya existen insumos, saltando seeder.');
            return;
        }

        $ingredients = [
            // Carnes
            ['sku' => 'CARNE-RES-001', 'name' => 'Carne de res', 'dimension' => 'mass', 'unit' => 'gram', 'stock' => 25000, 'min' => 5000, 'cost' => 8.5],
            ['sku' => 'CARNE-CERDO-002', 'name' => 'Carne de cerdo', 'dimension' => 'mass', 'unit' => 'gram', 'stock' => 20000, 'min' => 5000, 'cost' => 6.0],
            ['sku' => 'POLLO-PECHUGA-003', 'name' => 'Pechuga de pollo', 'dimension' => 'mass', 'unit' => 'gram', 'stock' => 18000, 'min' => 5000, 'cost' => 4.5],
            ['sku' => 'CAMARON-004', 'name' => 'Camarones', 'dimension' => 'mass', 'unit' => 'gram', 'stock' => 3000, 'min' => 5000, 'cost' => 12.0], // Stock bajo
            
            // Vegetales
            ['sku' => 'VERDURAS-MIXTAS-005', 'name' => 'Verduras mixtas', 'dimension' => 'mass', 'unit' => 'gram', 'stock' => 15000, 'min' => 3000, 'cost' => 2.5],
            ['sku' => 'BROCOLI-006', 'name' => 'Brócoli', 'dimension' => 'mass', 'unit' => 'gram', 'stock' => 8000, 'min' => 2000, 'cost' => 3.0],
            ['sku' => 'ZANAHORIA-007', 'name' => 'Zanahoria', 'dimension' => 'mass', 'unit' => 'gram', 'stock' => 10000, 'min' => 2000, 'cost' => 1.5],
            ['sku' => 'CEBOLLIN-008', 'name' => 'Cebollín', 'dimension' => 'mass', 'unit' => 'gram', 'stock' => 2000, 'min' => 2500, 'cost' => 2.0], // Stock bajo
            
            // Salsas y condimentos
            ['sku' => 'SALSA-SOJA-009', 'name' => 'Salsa de soja', 'dimension' => 'volume', 'unit' => 'milliliter', 'stock' => 5000, 'min' => 1000, 'cost' => 0.008],
            ['sku' => 'ACEITE-SESAMO-010', 'name' => 'Aceite de sésamo', 'dimension' => 'volume', 'unit' => 'milliliter', 'stock' => 3000, 'min' => 500, 'cost' => 0.015],
            ['sku' => 'JENGIBRE-011', 'name' => 'Jengibre fresco', 'dimension' => 'mass', 'unit' => 'gram', 'stock' => 1500, 'min' => 500, 'cost' => 4.0],
            ['sku' => 'AJO-012', 'name' => 'Ajo', 'dimension' => 'mass', 'unit' => 'gram', 'stock' => 2500, 'min' => 500, 'cost' => 3.5],
            
            // Carbohidratos
            ['sku' => 'ARROZ-013', 'name' => 'Arroz', 'dimension' => 'mass', 'unit' => 'gram', 'stock' => 50000, 'min' => 10000, 'cost' => 1.2],
            ['sku' => 'FIDEOS-014', 'name' => 'Fideos de arroz', 'dimension' => 'mass', 'unit' => 'gram', 'stock' => 12000, 'min' => 3000, 'cost' => 2.0],
            
            // Otros
            ['sku' => 'HUEVO-015', 'name' => 'Huevo', 'dimension' => 'unit', 'unit' => 'unit', 'stock' => 120, 'min' => 30, 'cost' => 250],
            ['sku' => 'TOFU-016', 'name' => 'Tofu', 'dimension' => 'mass', 'unit' => 'gram', 'stock' => 0, 'min' => 2000, 'cost' => 3.0], // Sin stock
        ];

        foreach ($ingredients as $ing) {
            RawIngredient::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'sku' => $ing['sku'],
                'name_translations' => ['es' => $ing['name']],
                'dimension_type' => match($ing['dimension']) {
                    'mass' => DimensionType::MASS,
                    'volume' => DimensionType::VOLUME,
                    'unit' => DimensionType::UNIT,
                },
                'base_unit' => match($ing['unit']) {
                    'gram' => BaseUnit::GRAM,
                    'milliliter' => BaseUnit::MILLILITER,
                    'unit' => BaseUnit::UNIT,
                },
                'current_stock_base' => $ing['stock'],
                'minimum_stock_base' => $ing['min'],
                'cost_per_base_unit' => $ing['cost'],
                'is_active' => true,
            ]);
        }

        $this->command->info('✅ ' . count($ingredients) . ' insumos creados exitosamente.');
    }
}
